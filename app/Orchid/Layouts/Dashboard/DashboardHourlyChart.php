<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Chart;

class DashboardHourlyChart extends Chart
{
    /** @var string */
    protected $type = Chart::TYPE_LINE;

    /** @var int */
    protected $height = 350;

    /** @var string */
    protected $target = 'chart';

    /**
     * Configuring line.
     *
     * @var array
     */
    protected $lineOptions = [
        'spline'     => 1,
        'regionFill' => 0,
        'hideDots'   => 0,
        'hideLine'   => 0,
        'heatline'   => 0,
        'dotSize'    => 3,
    ];
}
