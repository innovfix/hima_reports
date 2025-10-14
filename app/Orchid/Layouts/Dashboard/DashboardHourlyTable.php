<?php

namespace App\Orchid\Layouts\Dashboard;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class DashboardHourlyTable extends Table
{
    protected $target = 'table';

    public function columns(): array
    {
        return [
            TD::make('hour', __('Hour')),
            TD::make('registered', __('Registrations')),
            TD::make('paid_users', __('Paid Users')),
            TD::make('paid_amount', __('Paid Amount'))->render(function ($row) {
                if (is_object($row) && method_exists($row, 'getContent')) {
                    return $row->getContent('paid_amount');
                }

                $value = 0;

                if (is_array($row)) {
                    $value = $row['paid_amount'] ?? 0;
                } elseif (is_object($row)) {
                    $value = $row->paid_amount ?? ($row->{'paid_amount'} ?? 0);
                }

                return number_format((float) $value, 2);
            }),
        ];
    }
}
