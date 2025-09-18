<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Platform\Models\User;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Persona;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Illuminate\Support\Facades\Schema;
use Orchid\Support\Color;

class UserListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'users';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        // Dynamically build columns from the users table so all columns are shown
        $columns = Schema::getColumnListing('users');

        $tds = [];

        // Exclude sensitive or unwanted columns from the table
        $exclude = [
            'password',
            'remember_token',
            'permissions',
        ];

        foreach ($columns as $column) {
            if (in_array($column, $exclude, true)) {
                continue;
            }

            // ID column: center and small width
            if ($column === 'id') {
                $tds[] = TD::make('id', __('Id'))
                    ->sort()
                    ->width('80px')
                    ->align(TD::ALIGN_CENTER)
                    ->render(fn (User $user) => (int) $user->id);

                continue;
            }

            // Add payouts_count column (computed in query) if available
            if ($column === 'payouts_count') {
                $tds[] = TD::make('payouts_count', __('Payouts'))
                    ->sort()
                    ->align(TD::ALIGN_CENTER)
                    ->width('110px')
            ->render(fn ($user) =>
                is_object($user)
                    ? (method_exists($user, 'get') ? e((string) ($user->get('payouts_count') ?? 0)) : e((string) ($user->payouts_count ?? 0)))
                    : e((string) ($user['payouts_count'] ?? 0))
            );

                continue;
            }

            // Numeric columns should be right aligned for better readability
            $numericCols = ['coins', 'total_coins', 'age', 'plan', 'storage_limit'];
            if (in_array($column, $numericCols, true)) {
                $tds[] = TD::make($column, __(ucfirst(str_replace('_', ' ', $column))))
                    ->sort()
                    ->align(TD::ALIGN_RIGHT)
                    ->width('90px')
                    ->render(fn (User $user) => is_numeric($user->{$column} ?? null) ? e((string) $user->{$column}) : e((string) ($user->{$column} ?? '')));

                continue;
            }

            // Gender badge
            if (in_array($column, ['gender', 'sex'], true)) {
                $tds[] = TD::make($column, __('Gender'))
                    ->sort()
                    ->align(TD::ALIGN_CENTER)
                    ->width('110px')
                    ->render(fn (User $user) => e((string) ($user->{$column} ?? '-')));
                continue;
            }

            // Interests / Describe yourself wider columns
            if (in_array($column, ['interests', 'describe_yourself', 'describe_yourself', 'describe yourself'], true)) {
                $tds[] = TD::make($column, __(ucfirst(str_replace('_', ' ', $column))))
                    ->sort()
                    ->width('220px')
                    ->align(TD::ALIGN_LEFT)
                    ->render(fn (User $user) => e((string) ($user->{$column} ?? '')));
                continue;
            }
            // Special rendering for name/email and timestamps
            if ($column === 'name') {
                // Render simple inline name with fixed width to avoid vertical wrapping
                $tds[] = TD::make('name', __('Name'))
                ->sort()
                    ->cantHide()
                    ->width('220px')
                    ->align(TD::ALIGN_LEFT)
                    ->render(fn (User $user) => e((string) ($user->name ?? '')));

                continue;
            }

            if ($column === 'mobile' || $column === 'email') {
                $tds[] = TD::make($column, __(ucfirst($column)))
                ->sort()
                    ->cantHide()
                    ->width('160px')
                    ->align(TD::ALIGN_LEFT)
                    ->render(fn (User $user) => e((string) ($user->{$column} ?? '')));

                continue;
            }

            if (in_array($column, ['created_at', 'updated_at', 'datetime'], true)) {
                $tds[] = TD::make($column, __(ucfirst(str_replace('_', ' ', $column))))
                    ->usingComponent(DateTimeSplit::class)
                    ->align(TD::ALIGN_LEFT)
                    ->defaultHidden()
                    ->sort();
                continue;
            }

            // Generic column with sorting and simple filter
            $td = TD::make($column, __(ucfirst(str_replace('_', ' ', $column))))
                ->sort()
                ->render(fn (User $user) =>
                    // Ensure we pass a string to the view helper; arrays/objects are JSON-encoded
                    is_array($user->{$column} ?? null) || is_object($user->{$column} ?? null)
                        ? e(json_encode($user->{$column}))
                        : e((string) ($user->{$column} ?? ''))
                );

            // Language filtering is handled globally via UserFiltersLayout

            $tds[] = $td;
        }

        // If withdrawals table exists, add payouts_count column (computed in query)
        if (Schema::hasTable('withdrawals')) {
            $tds[] = TD::make('payouts_count', __('Payouts'))
                ->sort()
                ->align(TD::ALIGN_CENTER)
                ->width('110px')
                ->render(function ($user) {
                    if (is_object($user)) {
                        if (method_exists($user, 'get')) {
                            return e((string) ($user->get('payouts_count') ?? 0));
                        }

                        return e((string) ($user->payouts_count ?? 0));
                    }

                    if (is_array($user)) {
                        return e((string) ($user['payouts_count'] ?? 0));
                    }

                    return e('0');
                });

            // First payout date column (computed in query)
            $tds[] = TD::make('first_payout_date', __('First Payout'))
                ->sort()
                ->align(TD::ALIGN_LEFT)
                ->width('140px')
                ->render(function ($user) {
                    $val = null;
                    if (is_object($user)) {
                        if (method_exists($user, 'get')) {
                            $val = $user->get('first_payout_date');
                        } else {
                            $val = $user->first_payout_date ?? null;
                        }
                    } elseif (is_array($user)) {
                        $val = $user['first_payout_date'] ?? null;
                    }

                    if (empty($val)) {
                        return '-';
                    }

                    return e((string) date('Y-m-d', strtotime($val)));
                });
        }

        // Actions column styled as a compact dropdown
        $tds[] = TD::make(__('Actions'))
            ->align(TD::ALIGN_CENTER)
            ->width('90px')
            ->render(function ($user) {
                // Resolve id whether $user is Eloquent model or Repository/array
                $id = null;
                if (is_object($user)) {
                    if (method_exists($user, 'get')) {
                        // Orchid Repository
                        $id = $user->get('id');
                    } elseif (property_exists($user, 'id') || isset($user->id)) {
                        $id = $user->id;
                    } elseif (isset($user->id)) {
                        $id = $user->id;
                    }
                } elseif (is_array($user)) {
                    $id = $user['id'] ?? null;
                }
                if (empty($id)) {
                    // No id available (aggregate/empty row) - don't render actions
                    return null;
                }

                return DropDown::make()
                    ->icon('bs.three-dots')
                    ->list([
                        Link::make(__('Edit'))->route('platform.systems.users.edit', $id)->icon('bs.pencil'),

                        Button::make(__('Delete'))
                            ->type(Color::DANGER)
                            ->icon('bs.trash3')
                            ->confirm(__('Once the account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'))
                            ->method('remove', ['id' => $id]),
                    ]);
            });

        return $tds;
    }
}
