<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Platform\Models\Role;
use Orchid\Screen\Fields\Select;
use Illuminate\Support\Facades\Schema;

class RoleFilter extends Filter
{
    /**
     * The displayable name of the filter.
     *
     * @return string
     */
    public function name(): string
    {
        return __('Roles');
    }

    /**
     * The array of matched parameters.
     *
     * @return array
     */
    public function parameters(): array
    {
        return ['role'];
    }

    /**
     * Apply to a given Eloquent query builder.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function run(Builder $builder): Builder
    {
        // If roles table or expected column doesn't exist, do not modify query
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'slug')) {
            return $builder;
        }

        try {
            return $builder->whereHas('roles', function (Builder $query) {
                $query->where('slug', $this->request->get('role'));
            });
        } catch (\Throwable $e) {
            // If schema is unexpected, silently skip applying the filter
            return $builder;
        }
    }

    /**
     * Get the display fields.
     */
    public function display(): array
    {
        // If roles table or required columns don't exist, hide the filter
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'slug') || ! Schema::hasColumn('roles', 'name')) {
            return [];
        }

        try {
            return [
                Select::make('role')
                    ->fromModel(Role::class, 'name', 'slug')
                    ->empty()
                    ->value($this->request->get('role'))
                    ->title(__('Roles')),
            ];
        } catch (\Throwable $e) {
            // If the roles table schema is different, don't display the filter
            return [];
        }
    }

    /**
     * Value to be displayed
     */
    public function value(): string
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'slug')) {
            return $this->name().': '.__('All');
        }

        try {
            $role = Role::where('slug', $this->request->get('role'))->first();
            return $this->name().': '.($role?->name ?? __('All'));
        } catch (\Throwable $e) {
            return $this->name().': '.__('All');
        }
    }
}
