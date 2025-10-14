<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Layout;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout as LayoutBuilder;

class DashboardTotalsLayout extends Layout
{
    protected $target = 'totals';

    protected $template = 'dashboard.totals';

    public function build(array $repository = [])
    {
        $totals = $repository[$this->target] ?? [];

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

        return LayoutBuilder::view('platform.dashboard.totals', [
            'metrics' => $metrics,
        ]);
    }
}
