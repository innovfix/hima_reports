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
use App\Orchid\Layouts\Dashboard\DashboardHourlyChart;
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

        if (Schema::hasTable($usersTable)) {
            $registrationQuery = DB::table($usersTable)
                ->select(DB::raw('DATE_FORMAT(created_at, "%H:00") as hour'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$start, $end])
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

        if (Schema::hasTable($transactionsTable)) {
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
                $paymentsQuery = DB::table($transactionsTable)
                    ->select(
                        DB::raw('DATE_FORMAT(created_at, "%H:00") as hour'),
                        DB::raw("COUNT(DISTINCT {$userColumn}) as paid_users"),
                        DB::raw(
                            $amountColumn
                                ? "SUM({$amountColumn})"
                                : ($coinsColumn ? "SUM({$coinsColumn})" : '0')
                        . ' as paid_amount')
                    )
                    ->whereBetween('created_at', [$start, $end])
                    ->whereRaw("LOWER({$typeColumn}) = 'add_coins'")
                    ->groupBy(DB::raw('DATE_FORMAT(created_at, "%H:00")'))
                    ->get()
                    ->keyBy('hour');

                if ($paymentsQuery->isNotEmpty()) {
                    $hourlyStats = $hourlyStats->map(function ($slot) use ($paymentsQuery, $amountColumn, $coinsColumn) {
                        $hourKey = $slot['hour'];
                        if ($paymentsQuery->has($hourKey)) {
                            $row = $paymentsQuery->get($hourKey);
                            $slot['paid_users'] = (int) ($row->paid_users ?? 0);
                            $amountValue = (float) ($row->paid_amount ?? 0);
                            if (!$amountColumn && $coinsColumn) {
                                $amountValue = $amountValue / 100;
                            }
                            $slot['paid_amount'] = $amountValue;
                        }
                        return $slot;
                    });
                }
            }
        }

        $hourlyStats = $hourlyStats->values();

        $labels = $hourlyStats->pluck('hour')->toArray();
        $registrationSeries = $hourlyStats->pluck('registered')->toArray();
        $paidSeries = $hourlyStats->pluck('paid_users')->toArray();

        $chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'name' => 'New Registrations',
                    'values' => $registrationSeries,
                ],
                [
                    'name' => 'Paid Users',
                    'values' => $paidSeries,
                ],
            ],
        ];

        $tableData = $hourlyStats
            ->map(function ($item) {
                return array_merge($item, [
                    'paid_amount' => number_format((float) $item['paid_amount'], 2),
                ]);
            })
            ->map(fn ($item) => new Repository($item))
            ->values();

        $totals = [
            'registered' => $hourlyStats->sum('registered'),
            'paid_users' => $hourlyStats->sum('paid_users'),
            'paid_amount' => number_format($hourlyStats->sum('paid_amount'), 2),
        ];

        return [
            'chart' => $chartData,
            'table' => $tableData,
            'selected_date' => $dateFilter,
            'totals' => $totals,
        ];
    }

    public function name(): ?string
    {
        return __('Dashboard Overview');
    }

    public function description(): ?string
    {
        return __('Hourly registrations and payouts.');
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

            Layout::block(DashboardHourlyChart::class)
                ->title(__('Hourly Overview'))
                ->description(__('New registrations and paid users per hour.')),

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
