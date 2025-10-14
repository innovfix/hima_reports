<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layout;
use Orchid\Screen\Repository;
use Orchid\Support\Facades\Layout as LayoutFacade;

class DashboardTotalsLayout extends Layout
{
    protected $target = 'totals';

    public function build(Repository $repository): iterable
    {
        $totals = $repository->getContent($this->target) ?? [];

        $metrics = [
            [
                'title' => __('Total Registrations'),
                'value' => number_format((float) ($totals['registered'] ?? 0)),
            ],
            [
                'title' => __('Total Paid Users'),
                'value' => number_format((float) ($totals['paid_users'] ?? 0)),
            ],
            [
                'title' => __('Total Paid Amount (₹)'),
                'value' => '₹ '.number_format((float) ($totals['paid_amount'] ?? 0), 2),
            ],
        ];

        return [
            LayoutFacade::view('platform.dashboard.totals', [
                'metrics' => $metrics,
            ]),
        ];
    }
}
