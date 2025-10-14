<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class DashboardHourlyChart extends Chart
{
    /** @var string */
    protected $type = 'line';

    /** @var int */
    protected $height = 320;

    /** @var string */
    protected $target = 'chart';

    /**
     * Chart options
     */
    protected function options(): array
    {
        return [
            'curve' => 'smooth',
            'dataLabels' => [
                'enabled' => true,
            ],
            'stroke' => [
                'width' => [2, 2, 3],
            ],
            'colors' => ['#1f77b4', '#ff7f0e', '#2ca02c'],
            'xaxis' => [
                'title' => __('Hour of Day'),
            ],
            'yaxis' => [
                [
                    'title' => __('Count'),
                ],
                [
                    'opposite' => true,
                    'title' => __('Paid Amount (₹)'),
                    'labels' => [
                        'formatter' => 'function (value) { return "₹ " + value.toFixed(2); }',
                    ],
                ],
            ],
            'tooltip' => [
                'shared' => true,
            ],
        ];
    }

    protected function datasets(): array
    {
        $datasets = parent::datasets();

        if (count($datasets) >= 3) {
            $datasets[2]['yaxis_index'] = 1;
        }

        return $datasets;
    }
}
