<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Finance;

use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class CreatorsPayoutListLayout extends Table
{
    /** @var string */
    protected $target = 'rows';

    public function columns(): array
    {
        $tds = [];

        // Prepend creator info columns
        $tds[] = TD::make('creator_id', __('Creator ID'))->sort()->width('120px')->align(TD::ALIGN_CENTER);
        $tds[] = TD::make('creator_name', __('Creator Name'))->sort()->width('200px');
        $tds[] = TD::make('creator_language', __('Language'))->sort()->width('140px');

        // Dynamically add withdrawal table columns excluding sensitive or duplicates
        $excluded = ['user_id'];
        if (Schema::hasTable('withdrawals')) {
            foreach (Schema::getColumnListing('withdrawals') as $col) {
                if (in_array($col, $excluded, true)) {
                    continue;
                }

                // Render a colored badge for withdrawal status
                if ($col === 'status') {
                    $tds[] = TD::make('status', __('Status'))
                        ->render(function ($row) {
                            $value = null;

                            try {
                                $value = $row->getContent('status');
                            } catch (\Throwable $e) {
                                // Fallback for arrays or unexpected types
                                $value = is_array($row) ? ($row['status'] ?? null) : null;
                            }

                            return match ($value) {
                                0, '0' => "<span class='badge bg-primary'>".__('Pending')."</span>",
                                1, '1' => "<span class='badge bg-success'>".__('Paid')."</span>",
                                2, '2' => "<span class='badge bg-danger'>".__('Rejected')."</span>",
                                default => "<span class='badge bg-secondary'>".e($value)."</span>",
                            };
                        })
                        ->sort();

                    continue;
                }

                $tds[] = TD::make($col, __(ucfirst(str_replace('_',' ', $col))))->sort();
            }
        }

        return $tds;
    }
}



