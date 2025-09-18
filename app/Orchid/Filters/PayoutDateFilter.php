<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\DateTimer;
use Illuminate\Support\Facades\Schema;

class PayoutDateFilter extends Filter
{
    public function name(): string
    {
        return __('First payout date');
    }

    public function parameters(): array
    {
        return ['payout_from', 'payout_to'];
    }

    public function run(Builder $builder): Builder
    {
        if (! Schema::hasTable('withdrawals')) {
            return $builder;
        }

        $from = $this->request->get('payout_from');
        $to = $this->request->get('payout_to');

        if (empty($from) && empty($to)) {
            return $builder;
        }

        // Build correlated subquery for min(created_at) per user and apply bounds
        // Compare by DATE(...) so a "to" date includes the whole day (inclusive)
        if (! empty($from)) {
            $builder->whereRaw("DATE((select min(created_at) from withdrawals where withdrawals.user_id = users.id and status = 'paid')) >= ?", [$from]);
        }

        if (! empty($to)) {
            $builder->whereRaw("DATE((select min(created_at) from withdrawals where withdrawals.user_id = users.id and status = 'paid')) <= ?", [$to]);
        }

        return $builder;
    }

    public function display(): array
    {
        return [
            DateTimer::make('payout_from')->inline()->format('Y-m-d')->title(__('From')),
            DateTimer::make('payout_to')->inline()->format('Y-m-d')->title(__('To')),
        ];
    }

    public function value(): string
    {
        $from = $this->request->get('payout_from');
        $to = $this->request->get('payout_to');

        if ($from || $to) {
            return $this->name().': '.trim(($from ?? '').' - '.($to ?? ''));
        }

        return $this->name().': '.__('All');
    }
}


