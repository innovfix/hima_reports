<?php

namespace App\Orchid\Screens\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Screen;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Repository;
use Orchid\Support\Facades\Layout;
use App\Orchid\Layouts\Dashboard\DashboardHourlyTable;

class DashboardScreen extends Screen
{
    public function query(): iterable
    {
        $dateFilter = request()->get('date', Carbon::now()->toDateString());
        $start = Carbon::parse($dateFilter)->startOfDay();
        $end = Carbon::parse($dateFilter)->endOfDay();

        $usersTable = 'users';
        $transactionsTable = 'transactions';

        $hourlyStats = collect(range(0, 23))->map(function ($hour) {
            return [
                'hour' => sprintf('%02d:00', $hour),
                'registered' => 0,
                'paid_users' => 0,
                'paid_amount' => 0,
            ];
        })->keyBy('hour');

        $genderColumn = null;
        if (Schema::hasTable($usersTable)) {
            foreach (['gender', 'sex', 'Gender'] as $column) {
                if (Schema::hasColumn($usersTable, $column)) {
                    $genderColumn = $column;
                    break;
                }
            }
        }

        $maleValues = ['male', 'm'];

        if (Schema::hasTable($usersTable) && $genderColumn !== null) {
            $registrationQuery = DB::table($usersTable)
                ->select(DB::raw('DATE_FORMAT(created_at, "%H:00") as hour'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$start, $end])
                ->whereIn(DB::raw('LOWER('.$usersTable.'.'.$genderColumn.')'), $maleValues)
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%H:00")'))
                ->pluck('count', 'hour');

            if ($registrationQuery->isNotEmpty()) {
                $hourlyStats = $hourlyStats->map(function ($slot) use ($registrationQuery) {
                    $hourKey = $slot['hour'];
                    if ($registrationQuery->has($hourKey)) {
                        $slot['registered'] = (int) $registrationQuery->get($hourKey, 0);
                    }
                    return $slot;
                });
            }
        }

        $todayRegisteredMaleIds = collect();

        if (Schema::hasTable($usersTable) && $genderColumn !== null) {
            $todayRegisteredMaleIds = DB::table($usersTable)
                ->whereBetween('created_at', [$start, $end])
                ->whereIn(DB::raw('LOWER('.$usersTable.'.'.$genderColumn.')'), $maleValues)
                ->pluck('id');
        }

        if (Schema::hasTable($transactionsTable) && $todayRegisteredMaleIds->isNotEmpty()) {
            $typeColumn = Schema::hasColumn($transactionsTable, 'type') ? 'type' : null;
            $userColumn = Schema::hasColumn($transactionsTable, 'user_id') ? 'user_id' : null;

            $amountColumn = null;
            $coinsColumn = null;
            foreach (['amount', 'coins', 'value'] as $candidate) {
                if (Schema::hasColumn($transactionsTable, $candidate)) {
                    if ($candidate === 'coins') {
                        $coinsColumn = $candidate;
                    } else {
                        $amountColumn = $candidate;
                    }
                    break;
                }
            }

            if ($typeColumn && $userColumn && ($amountColumn || $coinsColumn)) {
                $amountExpression = $amountColumn
                    ? "t.{$amountColumn}"
                    : ($coinsColumn ? "t.{$coinsColumn}" : '0');

                $nameColumn = null;
                if (Schema::hasTable($usersTable)) {
                    foreach (['name', 'username'] as $candidate) {
                        if (Schema::hasColumn($usersTable, $candidate)) {
                            $nameColumn = $candidate;
                            break;
                        }
                    }
                }

                $paymentsQuery = DB::table($transactionsTable.' as t')
                    ->selectRaw('DATE_FORMAT(t.created_at, "%H:00") as hour')
                    ->selectRaw('t.'.$userColumn.' as user_id')
                    ->selectRaw($amountExpression.' as raw_amount')
                    ->whereBetween('t.created_at', [$start, $end])
                    ->whereRaw("LOWER(t.{$typeColumn}) = 'add_coins'")
                    ->whereIn('t.'.$userColumn, $todayRegisteredMaleIds);

                if (Schema::hasTable($usersTable)) {
                    $paymentsQuery->leftJoin($usersTable.' as u', 'u.id', '=', 't.'.$userColumn);

                    if ($genderColumn !== null) {
                        $paymentsQuery->whereIn(DB::raw('LOWER(u.'.$genderColumn.')'), $maleValues);
                    }

                    if ($nameColumn !== null) {
                        $paymentsQuery->addSelect(DB::raw('COALESCE(u.'.$nameColumn.', CONCAT("User #", t.'.$userColumn.')) as user_name'));
                    } else {
                        $paymentsQuery->addSelect(DB::raw('CONCAT("User #", t.'.$userColumn.') as user_name'));
                    }
                } else {
                    $paymentsQuery->addSelect(DB::raw('CONCAT("User #", t.'.$userColumn.') as user_name'));
                }

                $payments = $paymentsQuery->get()->groupBy('hour');

                if ($payments->isNotEmpty()) {
                    $hourlyStats = $hourlyStats->map(function ($slot) use ($payments, $amountColumn, $coinsColumn) {
                        $hourKey = $slot['hour'];
                        $details = $payments->get($hourKey, collect());

                        if ($details->isNotEmpty()) {
                            $slot['paid_users'] = $details->pluck('user_id')->unique()->count();
                            $slot['paid_amount'] = $details->sum(function ($row) use ($amountColumn, $coinsColumn) {
                                $amount = (float) ($row->raw_amount ?? 0);
                                if (!$amountColumn && $coinsColumn) {
                                    $amount = $amount / 100;
                                }
                                return $amount;
                            });

                            $slot['paid_details'] = $details->map(function ($row) use ($amountColumn, $coinsColumn) {
                                $amount = (float) ($row->raw_amount ?? 0);
                                if (!$amountColumn && $coinsColumn) {
                                    $amount = $amount / 100;
                                }

                                return [
                                    'user_id' => $row->user_id,
                                    'name' => $row->user_name ?? ('User #'.$row->user_id),
                                    'amount' => $amount,
                                ];
                            })->values();
                        } else {
                            $slot['paid_details'] = collect();
                        }

                        return $slot;
                    });
                }
            }
        }

        $hourlyStats = $hourlyStats->values();

        $tableData = $hourlyStats
            ->map(function ($item) {
                $details = collect($item['paid_details'] ?? []);

                $detailList = $details->isEmpty()
                    ? []
                    : $details->map(function ($row) {
                        $amount = (float) ($row['amount'] ?? 0);
                        $name = $row['name'] ?? ('User #'.$row['user_id']);
                        return [
                            'name' => $name,
                            'amount' => $amount,
                        ];
                    })->values()->all();

                return new Repository([
                    'hour' => $item['hour'] ?? '',
                    'registered' => $item['registered'] ?? 0,
                    'paid_users' => $item['paid_users'] ?? 0,
                    'paid_amount' => (float) ($item['paid_amount'] ?? 0),
                    'paid_details' => $detailList,
                ]);
            })
            ->values();

        $totals = [
            'registered' => $hourlyStats->sum('registered'),
            'paid_users' => $hourlyStats->sum('paid_users'),
            'paid_amount' => $hourlyStats->sum('paid_amount'),
        ];

        return [
            'table' => $tableData,
            'selected_date' => $dateFilter,
            'totals' => $totals,
        ];
    }

    public function name(): ?string
    {
        return __('Male Users Dashboard');
    }

    public function description(): ?string
    {
        return __('Male registrations and payouts for the selected day.');
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('filters.date')
                    ->type('date')
                    ->title(__('Select Date'))
                    ->value(request()->get('date', Carbon::now()->toDateString())),

                Button::make(__('Apply'))
                    ->icon('bs.funnel')
                    ->method('filterByDate'),
            ])->title(__('Filters')),

            DashboardHourlyTable::class,
        ];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make(__('Today'))
                ->icon('bs-calendar-day')
                ->route('platform.dashboard', ['date' => Carbon::now()->toDateString()]),

            Link::make(__('Go to Users'))
                ->icon('bs-people')
                ->route('platform.systems.users'),
        ];
    }

    public function filterByDate()
    {
        $filters = request()->get('filters', []);

        $params = array_filter([
            'date' => $filters['date'] ?? null,
        ]);

        return redirect()->route('platform.dashboard', $params);
    }
}
