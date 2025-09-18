<?php

namespace App\Orchid\Layouts\User;

use App\Orchid\Filters\RoleFilter;
use App\Orchid\Filters\LanguageFilter;
use App\Orchid\Filters\SearchFilter;
use App\Orchid\Filters\PayoutsFilter;
use App\Orchid\Filters\PayoutDateFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class UserFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            SearchFilter::class,
            RoleFilter::class,
            LanguageFilter::class,
            PayoutsFilter::class,
            PayoutDateFilter::class,
        ];
    }
}
