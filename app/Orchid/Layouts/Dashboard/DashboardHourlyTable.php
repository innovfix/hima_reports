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
                $value = 0;

                if (is_object($row) && method_exists($row, 'getContent')) {
                    $value = $row->getContent('paid_amount');
                } elseif (is_array($row)) {
                    $value = $row['paid_amount'] ?? 0;
                } elseif (is_object($row)) {
                    $value = $row->paid_amount ?? ($row->{'paid_amount'} ?? 0);
                }

                return '₹ '.number_format((float) $value, 2);
            }),
            TD::make('paid_details', __('Paid Detail'))->render(function ($row) {
                $value = null;

                if (is_object($row) && method_exists($row, 'getContent')) {
                    $value = $row->getContent('paid_details');
                } elseif (is_array($row)) {
                    $value = $row['paid_details'] ?? null;
                }

                if (empty($value) || $value === '-') {
                    return '-';
                }

                $items = is_array($value) ? $value : explode(',', (string) $value);

                return '<ul class="list-unstyled mb-0">'.collect($items)->map(function ($item) {
                    return '<li>'.e(trim($item)).'</li>';
                })->implode('').' </ul>';
            }),
        ];
    }
}
