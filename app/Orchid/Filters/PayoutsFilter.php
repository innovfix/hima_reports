<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        // Build a grouped subquery once and join it for performant filtering
        $paidCountsSub = DB::table('withdrawals')
            ->selectRaw('user_id, COUNT(*) as paid_cnt')
            ->where('status', 1)
            ->groupBy('user_id');

        $builder->leftJoinSub($paidCountsSub, 'w_paid', function ($join) {
            $join->on('w_paid.user_id', '=', 'users.id');
        });

        // Ensure users.* is selected to avoid ambiguous columns after join
        $builder->select('users.*');

        return match ($val) {
            '0'   => $builder->whereNull('w_paid.paid_cnt'),
            '1'   => $builder->where('w_paid.paid_cnt', '=', 1),
            'gt1' => $builder->where('w_paid.paid_cnt', '>', 1),
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


