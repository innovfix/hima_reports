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
            TD::make('paid_details', __('Paid Detail'))->render(function ($row) {
                if (is_object($row) && method_exists($row, 'getContent')) {
                    $details = $row->getContent('paid_details');
                } elseif (is_array($row)) {
                    $details = $row['paid_details'] ?? [];
                } else {
                    $details = [];
                }

                if (empty($details)) {
                    return '-';
                }

                $items = collect($details)->map(function ($detail) {
                    $name = $detail['name'] ?? '';
                    $amount = number_format((float) ($detail['amount'] ?? 0), 2);
                    return '<li>'.e($name).' (₹ '.$amount.')</li>';
                })->implode('');

                return '<ul class="list-unstyled mb-0">'.$items.'</ul>';
            }),
        ];
    }
}
