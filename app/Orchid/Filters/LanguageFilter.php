<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LanguageFilter extends Filter
{
    public function name(): string
    {
        return __('Language');
    }

    public function parameters(): array
    {
        return ['language'];
    }

    public function run(Builder $builder): Builder
    {
        if (! Schema::hasTable('users')) {
            return $builder;
        }

        $lang = $this->request->get('language');

        if (empty($lang)) {
            return $builder;
        }

        // Determine which column exists and only filter on existing column(s)
        $column = null;
        if (Schema::hasColumn('users', 'language')) {
            $column = 'language';
        } elseif (Schema::hasColumn('users', 'lang')) {
            $column = 'lang';
        }

        if ($column === null) {
            return $builder;
        }

        return $builder->where($column, $lang);
    }

    public function display(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        try {
            $col = Schema::hasColumn('users', 'language') ? 'language' : (Schema::hasColumn('users', 'lang') ? 'lang' : null);

            if ($col === null) {
                return [];
            }

            $options = DB::table('users')
                ->whereNotNull($col)
                ->distinct()
                ->orderBy($col)
                ->pluck($col)
                ->filter()
                ->values()
                ->mapWithKeys(fn ($v) => [$v => ucfirst((string) $v)])
                ->toArray();

            return [
                Select::make('language')
                    ->options($options)
                    ->empty()
                    ->value($this->request->get('language'))
                    ->title(__('Language')),
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function value(): string
    {
        $val = $this->request->get('language');
        return $this->name().': '.($val ? ucfirst((string) $val) : __('All'));
    }
}


