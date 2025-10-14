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
                'width' => 3,
            ],
            'xaxis' => [
                'title' => __('Hour of Day'),
            ],
            'yaxis' => [
                [
                    'title' => __('Count'),
                ],
            ],
        ];
    }
}
