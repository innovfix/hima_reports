<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class SearchFilter extends Filter
{
    public function name(): string
    {
        return __('Search');
    }

    public function parameters(): array
    {
        return ['q'];
    }

    public function run(Builder $builder): Builder
    {
        $q = $this->request->get('q');

        if (empty($q)) {
            return $builder;
        }

        return $builder->where(function ($b) use ($q) {
            $b->where('name', 'like', "%{$q}%")
                ->orWhere('mobile', 'like', "%{$q}%");
        });
    }

    public function display(): array
    {
        return [
            Input::make('q')
                ->title(__('Search'))
                ->placeholder(__('Search by name or mobile'))
                ->value($this->request->get('q')),
        ];
    }

    public function value(): string
    {
        $val = $this->request->get('q');
        return $this->name().': '.($val ? $val : __('All'));
    }
}


