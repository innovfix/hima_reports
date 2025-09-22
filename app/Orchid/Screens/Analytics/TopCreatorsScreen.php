<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Analytics;

use App\Orchid\Layouts\Analytics\TopCreatorsLayout;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Repository as OrchidRepository;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\TD;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class TopCreatorsScreen extends Screen
{
    /**
     * Query data.
     */
    public function query(): iterable
    {
        $usersTable = 'users';

        if (! Schema::hasTable($usersTable)) {
            return ['rows' => collect()];
        }

        // Detect a calls table from common names
        $possibleCallsTables = [
            'calls', 'call_logs', 'user_calls', 'voice_calls', 'callhistory', 'call_history'
        ];

        $callsTable = null;
        foreach ($possibleCallsTables as $tbl) {
            if (Schema::hasTable($tbl)) {
                $callsTable = $tbl;
                break;
            }
        }

        if ($callsTable === null) {
            // No calls table found; return empty set gracefully
            return ['rows' => collect()];
        }

        // Detect creator/user id column in calls
        // Prefer explicit call_user_id/creator columns; fall back to generic user_id
        $possibleCreatorCols = ['call_user_id', 'creator_id', 'receiver_id', 'callee_id', 'to_user_id', 'to_id', 'called_user_id', 'provider_id', 'user_id'];
        $creatorIdCol = null;
        foreach ($possibleCreatorCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) {
                $creatorIdCol = $col;
                break;
            }
        }

        if ($creatorIdCol === null) {
            return ['rows' => collect()];
        }

        // Media filter: audio, video, or all (default audio)
        $media = request()->get('media', 'audio');

        $audioWhere = function (QueryBuilder $q) use ($callsTable, $media): void {
            if ($media === 'all') {
                return;
            }

            // prefer type/call_type fields; support media/is_audio/is_video too
            if (Schema::hasColumn($callsTable, 'type')) {
                if ($media === 'audio') {
                    $q->whereIn(DB::raw('LOWER(type)'), ['audio', 'voice', 'audio_call']);
                } else {
                    $q->whereIn(DB::raw('LOWER(type)'), ['video', 'video_call']);
                }
                return;
            }

            if (Schema::hasColumn($callsTable, 'call_type')) {
                if ($media === 'audio') {
                    $q->whereIn(DB::raw('LOWER(call_type)'), ['audio', 'voice', 'audio_call']);
                } else {
                    $q->whereIn(DB::raw('LOWER(call_type)'), ['video', 'video_call']);
                }
                return;
            }

            if ($media === 'audio' && Schema::hasColumn($callsTable, 'is_audio')) {
                $q->where('is_audio', 1);
                return;
            }

            if ($media === 'video' && Schema::hasColumn($callsTable, 'is_video')) {
                $q->where('is_video', 1);
                return;
            }

            if (Schema::hasColumn($callsTable, 'media')) {
                if ($media === 'audio') {
                    $q->whereIn(DB::raw('LOWER(media)'), ['audio', 'voice']);
                } else {
                    $q->whereIn(DB::raw('LOWER(media)'), ['video']);
                }
                return;
            }

            // Fallback: if we can't detect media, allow all (safe default)
            return;
        };

        // Detect timestamp / start column for calls (we'll also prefer created_at as date)
        $possibleTsCols = ['created_at', 'started_at', 'start_time', 'call_time', 'began_at', 'createdOn'];
        $timestampCol = null;
        foreach ($possibleTsCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) {
                $timestampCol = $col;
                break;
            }
        }
        if ($timestampCol === null) {
            return ['rows' => collect()];
        }

        // Only consider explicit started and ended columns for duration calculation
        // include started_time/ended_time present in your schema
        $possibleEndCols = ['ended_at', 'end_time', 'ended_time'];
        $endCol = null;
        foreach ($possibleEndCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) { $endCol = $col; break; }
        }

        // Only use explicit start columns for duration calculation
        // include started_time present in your schema
        $possibleStartCols = ['started_at', 'start_time', 'started_time'];
        $startCol = null;
        foreach ($possibleStartCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) { $startCol = $col; break; }
        }

        // Use created_at as the date component (must exist to combine with time-only fields)
        $dateCol = Schema::hasColumn($callsTable, 'created_at') ? 'created_at' : null;

        // Determine period: this_week, last_week, all
        $period = request()->get('period', 'this_week');

        if ($period === 'last_week') {
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end = Carbon::now()->subWeek()->endOfWeek();
        } elseif ($period === 'all') {
            $start = null;
            $end = null;
        } else {
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
        }

        // detect duration column (seconds)
        $possibleDurationCols = ['duration_seconds','duration','call_duration','duration_secs','length_seconds','length','duration_ms'];
        $durationCol = null;
        foreach ($possibleDurationCols as $c) {
            if (Schema::hasColumn($callsTable, $c)) { $durationCol = $c; break; }
        }

        // compute days in period for averaging
        $daysCount = null;
        if ($start && $end) {
            $daysCount = $start->diffInDays($end) + 1;
        }

        // Subquery: per-period audio call counts and total duration per creator (only completed calls if end column exists)
        $selects = [$creatorIdCol.' as creator_id', DB::raw('COUNT(*) as weekly_audio_calls')];
        if ($durationCol) {
            $selects[] = DB::raw("SUM({$callsTable}.{$durationCol}) as total_seconds");
        } elseif ($startCol && $endCol && $dateCol) {
            // compute duration from date + start/end times (handles time-only start/end)
            $startExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$startCol})";
            $endExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$endCol})";
            $selects[] = DB::raw("SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, {$startExpr}, {$endExpr}))) as total_seconds");
        } else {
            $selects[] = DB::raw('0 as total_seconds');
        }

        $weeklySub = DB::table($callsTable)
            ->select($selects)
            ->when($start && $end, fn ($q) => $q->whereBetween($timestampCol, [$start, $end]))
            ->tap(function (QueryBuilder $q) use ($audioWhere) { $audioWhere($q); })
            ->when($endCol, function ($q) use ($callsTable, $endCol) {
                // exclude rows where end column is null or empty
                $q->whereNotNull($callsTable.'.'.$endCol)->where($callsTable.'.'.$endCol, '<>', '');
            })
            ->groupBy($creatorIdCol);

        // Detect columns on users table
        $nameCol = Schema::hasColumn($usersTable, 'name') ? 'name' : 'username';
        $languageCol = Schema::hasColumn($usersTable, 'language') ? 'language' : (Schema::hasColumn($usersTable, 'lang') ? 'lang' : null);
        $audioStatusCol = Schema::hasColumn($usersTable, 'audio_status') ? 'audio_status' : (Schema::hasColumn($usersTable, 'audio') ? 'audio' : null);
        $videoStatusCol = Schema::hasColumn($usersTable, 'video_status') ? 'video_status' : (Schema::hasColumn($usersTable, 'video') ? 'video' : null);

        // Detect gender column and apply female filter
        $genderCol = null;
        foreach (['gender', 'sex', 'Gender'] as $col) {
            if (Schema::hasColumn($usersTable, $col)) { $genderCol = $col; break; }
        }

        /** @var QueryBuilder $query */
        $query = DB::table($usersTable)
            ->select([
                $usersTable.'.id as creator_id',
                DB::raw($usersTable.'.'.$nameCol.' as creator_name'),
            ])
            ->leftJoinSub($weeklySub, 'wk', 'wk.creator_id', '=', $usersTable.'.id')
            ->whereNotNull('wk.weekly_audio_calls');

        if ($languageCol !== null) {
            $query->addSelect(DB::raw($usersTable.'.'.$languageCol.' as language'));
            // Apply language filter if provided
            $langFilter = request()->get('language');
            if (! empty($langFilter)) {
                $query->where($usersTable.'.'.$languageCol, $langFilter);
            }
        }
        if ($audioStatusCol !== null) {
            $query->addSelect(DB::raw($usersTable.'.'.$audioStatusCol.' as audio_status'));
        }
        if ($videoStatusCol !== null) {
            $query->addSelect(DB::raw($usersTable.'.'.$videoStatusCol.' as video_status'));
        }

        $query->addSelect(DB::raw('wk.weekly_audio_calls'));

        $hasDurationSource = $durationCol || ($startCol && $endCol && $dateCol);

        if ($hasDurationSource) {
            if ($daysCount) {
                $query->addSelect(DB::raw("ROUND(wk.total_seconds / {$daysCount} / 60, 2) as avg_minutes_per_day"));
            } else {
                $query->addSelect(DB::raw("ROUND(wk.total_seconds / 60, 2) as avg_minutes_per_day"));
            }
        } else {
            if ($daysCount) {
                $query->addSelect(DB::raw("ROUND(wk.weekly_audio_calls / {$daysCount}, 2) as avg_calls_per_day"));
            } else {
                $query->addSelect(DB::raw('wk.weekly_audio_calls as avg_calls_per_day'));
            }
        }

        if ($genderCol !== null) {
            $query->whereIn(DB::raw('LOWER('.$usersTable.'.'.$genderCol.')'), ['female', 'f']);
        }

        $query->orderByDesc('wk.weekly_audio_calls');

        $paginator = $query->paginate(20);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($item) => new OrchidRepository((array) $item))
        );

        return [
            'rows' => $paginator,
        ];
    }

    public function name(): ?string
    {
        return __('Top Creators');
    }

    public function description(): ?string
    {
        return __('Female creators ranked by audio calls this week.');
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        // Use inline Layout::table to avoid any layout closure arity issues
        return [
            Layout::table('rows', [
                TD::make('creator_id', __('Creator ID'))->sort()->width('120px')->align(TD::ALIGN_CENTER),
                TD::make('creator_name', __('Creator Name'))->sort()->width('220px'),
                TD::make('language', __('Language'))->sort()->width('140px')
                    ->render(function ($row) {
                        $val = null;
                        try { $val = $row->getContent('language'); } catch (\Throwable $e) { $val = is_array($row) ? ($row['language'] ?? null) : null; }
                        if (empty($val)) {
                            return '-';
                        }
                        // deterministic color per language — special-case Kannada to brown
                        $lower = strtolower(trim((string)$val));
                        if ($lower === 'kannada') {
                            $hex = 'a52a2a'; // brown
                        } else {
                            $hex = substr(md5($lower), 0, 6);
                        }
                        return "<span class='language-badge' style='background:#{$hex};color:#fff;padding:4px 8px;border-radius:12px;display:inline-block;'>".htmlspecialchars((string)$val)."</span>";
                    }),
                TD::make('audio_status', __('Audio Status'))->sort()->width('140px')
                    ->render(function ($row) {
                        $val = null;
                        try { $val = $row->getContent('audio_status'); } catch (\Throwable $e) { $val = is_array($row) ? ($row['audio_status'] ?? null) : null; }
                        return $val == 1 ? "<span class='badge bg-success'>".__('Active')."</span>" : "<span class='badge bg-secondary'>".__('Disabled')."</span>";
                    }),
                TD::make('video_status', __('Video Status'))->sort()->width('140px')
                    ->render(function ($row) {
                        $val = null;
                        try { $val = $row->getContent('video_status'); } catch (\Throwable $e) { $val = is_array($row) ? ($row['video_status'] ?? null) : null; }
                        return $val == 1 ? "<span class='badge bg-success'>".__('Active')."</span>" : "<span class='badge bg-secondary'>".__('Disabled')."</span>";
                    }),
                TD::make('weekly_audio_calls', __('Calls This Week'))->sort()->width('160px'),
                TD::make('avg_minutes_per_day', __('Avg Minutes/Day'))->sort()->width('160px')
                    ->render(function ($row) {
                        try { $val = $row->getContent('avg_minutes_per_day'); } catch (\Throwable $e) { $val = is_array($row) ? ($row['avg_minutes_per_day'] ?? null) : null; }
                        return $val !== null ? (string)$val : __('N/A');
                    }),
            ]),
        ];
    }

    public function commandBar(): iterable
    {
        $currentLanguage = request()->get('language', 'all');

        // prepare language column detection early so dropdown items can be colored
        $languageCol = Schema::hasColumn('users', 'language') ? 'language' : (Schema::hasColumn('users', 'lang') ? 'lang' : null);

        // build language items (DropDown::list expects an array of Actions)
        $languageItems = [];
        if ($languageCol && Schema::hasTable('users')) {
            $langs = DB::table('users')->select($languageCol)->whereNotNull($languageCol)->groupBy($languageCol)->pluck($languageCol)->toArray();
            foreach ($langs as $lang) {
                $lowerLang = strtolower(trim((string)$lang));
                if ($lowerLang === 'kannada') {
                    $hex = 'a52a2a';
                } else {
                    $hex = substr(md5($lowerLang), 0, 6);
                }
                $label = "<span class='language-dot' style='background:#{$hex};display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle;'></span>".htmlspecialchars((string)$lang);
                $languageItems[] = Link::make($label)
                    ->raw()
                    ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => $lang])))
                    ->class(strtolower((string)$currentLanguage) === strtolower((string)$lang) ? 'active-link' : '');
            }
            // 'All Languages' item
            $languageItems[] = Link::make('All Languages')
                ->raw()
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => 'all'])))
                ->class($currentLanguage === 'all' ? 'active-link' : '');
        }

        // Selected language indicator shown in the command bar (colored badge)
        $selectedIndicator = 'All Languages';
        $selectedColor = '999999';
        if ($currentLanguage && $currentLanguage !== 'all' && $languageCol) {
            $selectedIndicator = $currentLanguage;
            $lowerSel = strtolower(trim((string)$currentLanguage));
            $selectedColor = $lowerSel === 'kannada' ? 'a52a2a' : substr(md5($lowerSel), 0, 6);
        }

        return [
            // Period links
            \Orchid\Screen\Actions\Link::make('This week')
                ->icon('bs.calendar-week')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['period' => 'this_week'])))
                ->class(request()->get('period', 'this_week') === 'this_week' ? 'active-link' : ''),

            \Orchid\Screen\Actions\Link::make('Last week')
                ->icon('bs.calendar-week')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['period' => 'last_week'])))
                ->class(request()->get('period') === 'last_week' ? 'active-link' : ''),

            \Orchid\Screen\Actions\Link::make('All time')
                ->icon('bs.clock-history')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['period' => 'all'])))
                ->class(request()->get('period') === 'all' ? 'active-link' : ''),
            // Media filter links (default audio)
            \Orchid\Screen\Actions\Link::make('Audio')
                ->icon('bs-volume-up-fill')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['media' => 'audio'])))
                ->class(request()->get('media', 'audio') === 'audio' ? 'active-link' : ''),

            \Orchid\Screen\Actions\Link::make('Video')
                ->icon('bs-camera-video-fill')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['media' => 'video'])))
                ->class(request()->get('media') === 'video' ? 'active-link' : ''),

            \Orchid\Screen\Actions\Link::make('All')
                ->icon('bs-list')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['media' => 'all'])))
                ->class(request()->get('media') === 'all' ? 'active-link' : ''),
            // Selected language badge (shows currently applied language filter)
            // selected language HTML as string (use ->raw() so Orchid doesn't escape it)
            \Orchid\Screen\Actions\Link::make("<span class='language-dot' style='background:#{$selectedColor};display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle;'></span> " . htmlspecialchars((string)$selectedIndicator))
                ->raw()
                ->class('language-selected')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => $currentLanguage]))),

            // Language dropdown
            DropDown::make('Language')
                ->icon('bs-translate')
                ->list($languageItems)
                ->alignRight(),
        ];
    }
}


