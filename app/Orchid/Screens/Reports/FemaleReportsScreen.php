<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Reports;

use App\Orchid\Layouts\Reports\FemaleReportsLayout;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Repository as OrchidRepository;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\DateTimer;
use Orchid\Screen\Fields\Input;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Color;

class FemaleReportsScreen extends Screen
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

        // Detect calls table
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
            return ['rows' => collect()];
        }

        // Detect creator/user id column in calls
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

        // Detect timestamp column for calls
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

        // Detect call type column
        $typeCol = null;
        if (Schema::hasColumn($callsTable, 'type')) {
            $typeCol = 'type';
        } elseif (Schema::hasColumn($callsTable, 'call_type')) {
            $typeCol = 'call_type';
        }

        // Detect duration column (seconds)
        $possibleDurationCols = ['duration_seconds','duration','call_duration','duration_secs','length_seconds','length'];
        $durationCol = null;
        foreach ($possibleDurationCols as $c) {
            if (Schema::hasColumn($callsTable, $c)) { 
                $durationCol = $c; 
                break; 
            }
        }

        // Detect ended column for duration calculation
        $possibleEndCols = ['ended_at', 'end_time', 'ended_time'];
        $endCol = null;
        foreach ($possibleEndCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) { 
                $endCol = $col; 
                break; 
            }
        }

        // Detect start column
        $possibleStartCols = ['started_at', 'start_time', 'started_time'];
        $startCol = null;
        foreach ($possibleStartCols as $col) {
            if (Schema::hasColumn($callsTable, $col)) { 
                $startCol = $col; 
                break; 
            }
        }

        // Date column for combining with time-only fields
        $dateCol = Schema::hasColumn($callsTable, 'created_at') ? 'created_at' : null;

        // Get date filters from request
        $dateFrom = request()->get('date_from');
        $dateTo = request()->get('date_to');

        $start = null;
        $end = null;

        if (!empty($dateFrom)) {
            $start = Carbon::parse($dateFrom)->startOfDay();
        }
        if (!empty($dateTo)) {
            $end = Carbon::parse($dateTo)->endOfDay();
        }

        // Build ended condition for calls
        $endedCondition = '0';
        if ($durationCol) {
            $endedCondition = "{$callsTable}.{$durationCol} IS NOT NULL AND {$callsTable}.{$durationCol} > 0";
        } elseif ($endCol) {
            $endedCondition = "{$callsTable}.{$endCol} IS NOT NULL AND {$callsTable}.{$endCol} <> ''";
        }

        // Build subqueries for audio and video call durations separately
        $selects = [
            $creatorIdCol.' as creator_id',
            DB::raw('COUNT(*) as total_calls')
        ];

        // Audio calls count and duration
        if ($typeCol) {
            $selects[] = DB::raw("SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('audio', 'voice', 'audio_call') THEN 1 ELSE 0 END) as audio_calls");
            
            if ($durationCol) {
                $selects[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('audio', 'voice', 'audio_call') AND ({$endedCondition}) THEN {$callsTable}.{$durationCol} ELSE 0 END), 0) as audio_duration_seconds");
            } elseif ($startCol && $endCol && $dateCol) {
                $startExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$startCol})";
                $endExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$endCol})";
                $selects[] = DB::raw("SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('audio', 'voice', 'audio_call') AND ({$endedCondition}) THEN GREATEST(0, TIMESTAMPDIFF(SECOND, {$startExpr}, {$endExpr})) ELSE 0 END) as audio_duration_seconds");
            } else {
                $selects[] = DB::raw('0 as audio_duration_seconds');
            }

            // Video calls count and duration
            $selects[] = DB::raw("SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('video', 'video_call') THEN 1 ELSE 0 END) as video_calls");
            
            if ($durationCol) {
                $selects[] = DB::raw("COALESCE(SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('video', 'video_call') AND ({$endedCondition}) THEN {$callsTable}.{$durationCol} ELSE 0 END), 0) as video_duration_seconds");
            } elseif ($startCol && $endCol && $dateCol) {
                $startExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$startCol})";
                $endExpr = "CONCAT(DATE({$callsTable}.{$dateCol}),' ', {$callsTable}.{$endCol})";
                $selects[] = DB::raw("SUM(CASE WHEN LOWER({$callsTable}.{$typeCol}) IN ('video', 'video_call') AND ({$endedCondition}) THEN GREATEST(0, TIMESTAMPDIFF(SECOND, {$startExpr}, {$endExpr})) ELSE 0 END) as video_duration_seconds");
            } else {
                $selects[] = DB::raw('0 as video_duration_seconds');
            }
        } else {
            // If no type column, show all as combined
            $selects[] = DB::raw('0 as audio_calls');
            $selects[] = DB::raw('0 as audio_duration_seconds');
            $selects[] = DB::raw('0 as video_calls');
            $selects[] = DB::raw('0 as video_duration_seconds');
        }

        $callsSub = DB::table($callsTable)
            ->select($selects)
            ->when($start, fn ($q) => $q->where($timestampCol, '>=', $start))
            ->when($end, fn ($q) => $q->where($timestampCol, '<=', $end))
            ->groupBy($creatorIdCol);

        // Detect columns on users table
        $nameCol = Schema::hasColumn($usersTable, 'name') ? 'name' : 'username';
        $languageCol = Schema::hasColumn($usersTable, 'language') ? 'language' : (Schema::hasColumn($usersTable, 'lang') ? 'lang' : null);

        // Detect gender column
        $genderCol = null;
        foreach (['gender', 'sex', 'Gender'] as $col) {
            if (Schema::hasColumn($usersTable, $col)) { 
                $genderCol = $col; 
                break; 
            }
        }

        /** @var QueryBuilder $query */
        $query = DB::table($usersTable)
            ->select([
                $usersTable.'.id as creator_id',
                DB::raw($usersTable.'.'.$nameCol.' as creator_name'),
            ])
            ->leftJoinSub($callsSub, 'calls_data', 'calls_data.creator_id', '=', $usersTable.'.id')
            ->where('calls_data.total_calls', '>', 0);

        if ($languageCol !== null) {
            $query->addSelect(DB::raw($usersTable.'.'.$languageCol.' as language'));
            
            // Apply language filter if provided
            $langFilter = request()->get('language');
            if (! empty($langFilter) && $langFilter !== 'all') {
                $query->where($usersTable.'.'.$languageCol, $langFilter);
            }
        }

        // Add call statistics
        $query->addSelect(DB::raw('calls_data.total_calls'));
        $query->addSelect(DB::raw('calls_data.audio_calls'));
        $query->addSelect(DB::raw('ROUND(calls_data.audio_duration_seconds / 60, 2) as audio_call_duration'));
        $query->addSelect(DB::raw('calls_data.video_calls'));
        $query->addSelect(DB::raw('ROUND(calls_data.video_duration_seconds / 60, 2) as video_call_duration'));

        // Filter by female gender only
        if ($genderCol !== null) {
            $query->whereIn(DB::raw('LOWER('.$usersTable.'.'.$genderCol.')'), ['female', 'f']);
        }

        // Default sort: highest total calls first
        $query->orderByDesc('calls_data.total_calls');

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
        return __('Female Reports');
    }

    public function description(): ?string
    {
        return __('Female creators with audio and video call durations.');
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        return [
            // Date filters with submit button
            Layout::rows([
                Input::make('filters.date_from')
                    ->type('date')
                    ->title(__('Date From'))
                    ->value(request()->get('date_from'))
                    ->placeholder('YYYY-MM-DD'),
                
                Input::make('filters.date_to')
                    ->type('date')
                    ->title(__('Date To'))
                    ->value(request()->get('date_to'))
                    ->placeholder('YYYY-MM-DD'),
                    
                Button::make(__('Apply Date Filter'))
                    ->icon('bs.funnel')
                    ->method('filterByDate')
                    ->type(Color::PRIMARY),
            ])->title(__('Date Range Filter')),

            FemaleReportsLayout::class,
        ];
    }

    public function commandBar(): iterable
    {
        $currentLanguage = request()->get('language', 'all');
        
        // Build filter query with current values
        $filterParams = array_filter([
            'date_from' => request()->get('date_from'),
            'date_to' => request()->get('date_to'),
            'language' => request()->get('language'),
        ]);
        
        // Get available languages
        $languageCol = Schema::hasColumn('users', 'language') ? 'language' : (Schema::hasColumn('users', 'lang') ? 'lang' : null);
        $languageItems = [];
        
        if ($languageCol && Schema::hasTable('users')) {
            $langs = DB::table('users')
                ->select($languageCol)
                ->whereNotNull($languageCol)
                ->groupBy($languageCol)
                ->pluck($languageCol)
                ->toArray();
            
            foreach ($langs as $lang) {
                $lowerLang = strtolower(trim((string)$lang));
                if ($lowerLang === 'kannada') {
                    $hex = 'a52a2a';
                } else {
                    $hex = substr(md5($lowerLang), 0, 6);
                }
                
                $labelHtml = "<span class='language-dot' style='background:#{$hex};display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle;'></span>".htmlspecialchars((string)$lang);
                $languageItems[] = Link::make()
                    ->set('name', new \Illuminate\Support\HtmlString($labelHtml))
                    ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => $lang])))
                    ->class(strtolower((string)$currentLanguage) === strtolower((string)$lang) ? 'active-link' : '');
            }
            
            // 'All Languages' item
            $languageItems[] = Link::make('All Languages')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => 'all'])))
                ->class($currentLanguage === 'all' ? 'active-link' : '');
        }

        // Selected language indicator
        $selectedIndicator = 'All Languages';
        $selectedColor = '999999';
        if ($currentLanguage && $currentLanguage !== 'all' && $languageCol) {
            $selectedIndicator = $currentLanguage;
            $lowerSel = strtolower(trim((string)$currentLanguage));
            $selectedColor = $lowerSel === 'kannada' ? 'a52a2a' : substr(md5($lowerSel), 0, 6);
        }

        return [
            // Language dropdown
            DropDown::make('Language')
                ->icon('bs-translate')
                ->list($languageItems)
                ->alignRight(),
            
            // Selected language badge
            Link::make()
                ->set('name', new \Illuminate\Support\HtmlString("<span class='language-dot' style='background:#{$selectedColor};display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle;'></span> " . htmlspecialchars((string)$selectedIndicator)))
                ->class('language-selected')
                ->href(url()->current().'?'.http_build_query(array_merge(request()->all(), ['language' => $currentLanguage]))),
            
            // Clear filters button
            Link::make('Clear Filters')
                ->icon('bs.x-circle')
                ->href(url()->current())
                ->class('btn btn-sm btn-default'),
        ];
    }

    /**
     * Apply date filters
     */
    public function filterByDate()
    {
        $filters = request()->get('filters', []);
        
        $params = array_filter([
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'language' => request()->get('language'),
        ]);

        return redirect()->route('platform.reports.female_reports', $params);
    }
}

