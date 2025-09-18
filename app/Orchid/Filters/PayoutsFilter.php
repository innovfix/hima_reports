<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;
use Illuminate\Support\Facades\Schema;

class PayoutsFilter extends Filter
{
    public function name(): string
    {
        return __('Payouts');
    }

    public function parameters(): array
    {
        return ['payouts'];
    }

    public function run(Builder $builder): Builder
    {
        $val = $this->request->get('payouts');

        if ($val === null || $val === '') {
            return $builder;
        }

        // We rely on payouts_count computed in the main query. If not available, skip.
        if (! Schema::hasTable('withdrawals')) {
            return $builder;
        }

        // Use correlated subquery in WHERE to avoid HAVING/grouping issues when wrapped by pagination
        return match ($val) {
            '0' => $builder->whereRaw("(select count(*) from withdrawals where withdrawals.user_id = users.id and status = 'paid') = 0"),
            '1' => $builder->whereRaw("(select count(*) from withdrawals where withdrawals.user_id = users.id and status = 'paid') = 1"),
            'gt1' => $builder->whereRaw("(select count(*) from withdrawals where withdrawals.user_id = users.id and status = 'paid') > 1"),
            default => $builder,
        };
    }

    public function display(): array
    {
        return [
            Select::make('payouts')
                ->options([
                    '' => __('All'),
                    '0' => __('0 (No payouts)'),
                    '1' => __('1'),
                    'gt1' => __('More than 1'),
                ])
                ->empty()
                ->value($this->request->get('payouts'))
                ->title(__('Payouts')),
        ];
    }

    public function value(): string
    {
        $val = $this->request->get('payouts');
        return $this->name().': '.($val ? $val : __('All'));
    }
}


