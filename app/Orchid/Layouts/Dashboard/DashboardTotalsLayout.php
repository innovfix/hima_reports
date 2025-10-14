<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Layout;
use Orchid\Support\Facades\Layout as LayoutBuilder;

class DashboardTotalsLayout extends Layout
{
    protected $target = 'totals';

    protected function build(): array
    {
        $totals = $this->query->getContent($this->target) ?? [];

        $metrics = [
            [
                'title' => __('Total Registrations'),
                'value' => $totals['registered'] ?? 0,
            ],
            [
                'title' => __('Total Paid Users'),
                'value' => $totals['paid_users'] ?? 0,
            ],
            [
                'title' => __('Total Paid Amount (₹)'),
                'value' => '₹ '.number_format((float) ($totals['paid_amount'] ?? 0), 2),
            ],
        ];

        return [
            LayoutBuilder::view('platform.dashboard.totals', [
                'metrics' => $metrics,
            ]),
        ];
    }
}
