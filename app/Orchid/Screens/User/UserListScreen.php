<?php

declare(strict_types=1);

namespace App\Orchid\Screens\User;

use App\Orchid\Layouts\User\UserEditLayout;
use App\Orchid\Layouts\User\UserFiltersLayout;
use App\Orchid\Layouts\User\UserListLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Platform\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;

class UserListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // Optional debugging: pass debug_sql=1 to log executed SQL statements
        $debugSql = (bool) request()->boolean('debug_sql');
        if ($debugSql) {
            DB::connection()->enableQueryLog();
        }

        $query = User::query()->defaultSort('id', 'desc');

        // Eager-load roles when the pivot table exists
        if (Schema::hasTable('role_users')) {
            $query = $query->with('roles');
        }

        // Always apply filters defined in UserFiltersLayout (global filters such as Role/Language)
        $query = $query->filters(UserFiltersLayout::class);

        // Add a payouts_count subquery (number of 'paid' withdrawals per user) if withdrawals table exists
        if (Schema::hasTable('withdrawals')) {
            // Ensure we still select all user columns and only add the payouts_count subquery
            $query->select('users.*');

            $query->selectSub(function ($q) {
                $q->from('withdrawals')
                    ->selectRaw('count(*)')
                    ->whereColumn('withdrawals.user_id', 'users.id')
                    ->where('status', 1);
            }, 'payouts_count');

            // First paid payout date
            $query->selectSub(function ($q) {
                $q->from('withdrawals')
                    ->selectRaw('min(created_at)')
                    ->whereColumn('withdrawals.user_id', 'users.id')
                    ->where('status', 1);
            }, 'first_payout_date');
        }

        $users = $query->paginate();

        if ($debugSql) {
            try {
                Log::error('UserListScreen SQL', [
                    'params' => request()->all(),
                    'toSql' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                    'executed' => DB::getQueryLog(),
                    'count' => $users->total(),
                ]);
            } catch (\Throwable $e) {
                // ignore logging errors
            }
        }

        return [
            'users' => $users,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        // Hide the default header block (title) for this screen; breadcrumb remains.
        return null;
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return null;
    }

    public function permission(): ?iterable
    {
        return [
            'platform.systems.users',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            // Direct link to export route so browser can download the CSV (preserves query params)
            Link::make(__('Export CSV'))
                ->icon('bs.download')
                ->route('platform.systems.users.export'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            // Show filters above the table (no surrounding block) to avoid extra header card
            UserFiltersLayout::class,
            UserListLayout::class,

            Layout::modal('editUserModal', UserEditLayout::class)
                ->deferred('loadUserOnOpenModal'),
        ];
    }

    /**
     * Loads user data when opening the modal window.
     *
     * @return array
     */
    public function loadUserOnOpenModal(User $user): iterable
    {
        return [
            'user' => $user,
        ];
    }

    public function saveUser(Request $request, User $user): void
    {
        $request->validate([
            'user.email' => [
                'required',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
        ]);

        $user->fill($request->input('user'))->save();

        Toast::info(__('User was saved.'));
    }

    public function remove(Request $request): void
    {
        User::findOrFail($request->get('id'))->delete();

        Toast::info(__('User was removed'));
    }

    /**
     * Export filtered users to CSV
     */
    public function exportCsv(): StreamedResponse
    {
        $query = User::query()->defaultSort('id', 'desc');

        if (Schema::hasTable('role_users')) {
            $query = $query->with('roles');
        }

        $query = $query->filters(UserFiltersLayout::class);

        // include payouts_count and first_payout_date if available
        if (Schema::hasTable('withdrawals')) {
            $query->select('users.*');
            $query->selectSub(function ($q) {
                $q->from('withdrawals')->selectRaw('count(*)')->whereColumn('withdrawals.user_id', 'users.id')->where('status', 'paid');
            }, 'payouts_count');
            $query->selectSub(function ($q) {
                $q->from('withdrawals')->selectRaw('min(created_at)')->whereColumn('withdrawals.user_id', 'users.id')->where('status', 'paid');
            }, 'first_payout_date');
        }

        // Respect current pagination (export only the rows visible in UI page)
        $paginated = $query->paginate();
        $rows = collect($paginated->items());

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export-'.date('Y-m-d').'.csv"',
        ];

        // If columns param provided (from UI), limit columns
        $columnsParam = request()->get('columns');
        if (! empty($columnsParam)) {
            $columns = explode(',', $columnsParam);
        } else {
            $columns = $rows->isNotEmpty() ? array_keys((array) $rows->first()) : ['id','name','mobile','email'];
        }

        // Normalize and map friendly header names to actual row keys when possible
        $columnKeyCandidates = function ($label) {
            $label = trim($label);
            $lc = strtolower($label);

            // common explicit mappings for headers with spaces or different text
            $map = [
                'total coins' => 'total_coins',
                'total_coins' => 'total_coins',
                'audio status' => 'audio_status',
                'audio-status' => 'audio_status',
                'video status' => 'video_status',
                'video-status' => 'video_status',
                'first payout' => 'first_payout_date',
                'first_payout_date' => 'first_payout_date',
                'payouts' => 'payouts_count',
            ];

            $candidates = [];
            if (isset($map[$lc])) {
                $candidates[] = $map[$lc];
            }

            // snake_case variant
            $snake = preg_replace('/[^a-z0-9]+/i', '_', $lc);
            $snake = trim($snake, '_');
            $candidates[] = $snake;

            // raw lower-case
            $candidates[] = $lc;

            // camelCase variant
            $parts = explode('_', $snake);
            $camel = array_shift($parts);
            foreach ($parts as $p) { $camel .= ucfirst($p); }
            $candidates[] = $camel;

            return array_values(array_unique($candidates));
        };

        // Resolve the final column keys available in rows
        $availableKeys = $rows->isNotEmpty() ? array_keys((array) $rows->first()) : [];

        $finalColumns = array_map(function ($col) use ($columnKeyCandidates, $availableKeys) {
            // If column already matches available key, keep it
            if (in_array($col, $availableKeys, true)) {
                return $col;
            }

            // Try to map friendly label to key
            foreach ($columnKeyCandidates($col) as $candidate) {
                if (in_array($candidate, $availableKeys, true)) {
                    return $candidate;
                }
            }

            // Fallback to original
            return $col;
        }, $columns);

        $callback = function () use ($rows, $finalColumns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $finalColumns);

            foreach ($rows as $row) {
                $data = [];
                foreach ($finalColumns as $col) {
                    $val = $row->{$col} ?? ($row[$col] ?? '');
                    $data[] = is_array($val) || is_object($val) ? json_encode($val) : $val;
                }
                fputcsv($handle, $data);
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }
}
