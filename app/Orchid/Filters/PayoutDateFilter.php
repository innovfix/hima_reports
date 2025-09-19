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

        // Compute first paid date per user once, then join and filter by date bounds
        $firstPaidSub = \DB::table('withdrawals')
            ->selectRaw('user_id, MIN(created_at) as first_paid_date')
            ->where('status', 1)
            ->groupBy('user_id');

        $builder->leftJoinSub($firstPaidSub, 'first_paid', function ($join) {
            $join->on('first_paid.user_id', '=', 'users.id');
        });

        // Ensure only user columns are selected at this stage
        $builder->select('users.*');

        if (! empty($from)) {
            $builder->whereDate('first_paid.first_paid_date', '>=', $from);
        }

        if (! empty($to)) {
            $builder->whereDate('first_paid.first_paid_date', '<=', $to);
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


