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
            TD::make('paid_amount', __('Paid Amount'))->render(fn ($row) => number_format((float) ($row['paid_amount'] ?? 0), 2)),
        ];
    }
}
