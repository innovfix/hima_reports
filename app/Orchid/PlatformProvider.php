<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Orchid\Support\Color;
use Illuminate\Support\Facades\Route;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @param Dashboard $dashboard
     *
     * @return void
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // Register a small override stylesheet so custom admin styles load immediately
        // (public/css/hima-overrides.css is created and served directly)
        $dashboard->registerResource('stylesheets', asset('css/hima-overrides.css'));
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        // Simplified admin menu: only expose Users for this project
        return [
            Menu::make(__('Dashboard'))
                ->icon('bs.speedometer2')
                ->route('platform.dashboard')
                ->permission('platform.systems.users')
                ->title(__('Admin')),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Management')),

            Menu::make(__('Creators Payout'))
                ->icon('bs.wallet')
                ->route('platform.finance.creators_payout')
                ->permission('platform.systems.users'),

            Menu::make(__('Top Creators'))
                ->icon('bs.bar-chart')
                ->route('platform.analytics.top_creators')
                ->permission('platform.systems.users'),

            Menu::make(__('Female Reports'))
                ->icon('bs.file-earmark-bar-graph')
                ->route('platform.reports.female_reports')
                ->permission('platform.systems.users'),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),
        ];
    }
}
