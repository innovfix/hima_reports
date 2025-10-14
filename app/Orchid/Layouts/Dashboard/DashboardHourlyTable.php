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
                    $value = $row->getContent('paid_amount');
                } elseif (is_array($row)) {
                    $value = $row['paid_amount'] ?? 0;
                } else {
                    $value = 0;
                }

                return '₹ '.number_format((float) $value, 2);
            }),
           
        ];
    }
}
