<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Finance;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchid\Screen\Screen;
use Orchid\Screen\Repository as OrchidRepository;
use App\Orchid\Layouts\Finance\CreatorsPayoutListLayout;

class CreatorsPayoutListScreen extends Screen
{
    /**
     * Query data.
     */
    public function query(): iterable
    {
        $withdrawalsTable = 'withdrawals';
        $usersTable = 'users';

        /** @var QueryBuilder $query */
        $query = DB::table($withdrawalsTable);

        if (Schema::hasTable($usersTable) && Schema::hasTable($withdrawalsTable)) {
            // Explicit mapping: users.id <-> withdrawals.user_id
            $query = $query
                ->leftJoin($usersTable, $usersTable.'.id', '=', $withdrawalsTable.'.user_id')
                ->addSelect($withdrawalsTable.'.*')
                ->addSelect(DB::raw($usersTable.'.id as creator_id'))
                ->when(Schema::hasColumn($usersTable, 'name'), fn ($q) => $q->addSelect(DB::raw($usersTable.'.name as creator_name')))
                ->when(Schema::hasColumn($usersTable, 'language'), fn ($q) => $q->addSelect(DB::raw($usersTable.'.language as creator_language')))
                ->when(!Schema::hasColumn($usersTable, 'language') && Schema::hasColumn($usersTable, 'lang'), fn ($q) => $q->addSelect(DB::raw($usersTable.'.lang as creator_language')));
        }

        $paginator = $query->paginate(20);

        // Ensure each row item is an Orchid Repository so TD::buildTd can call ->getContent()
        $paginator->setCollection($paginator->getCollection()->map(fn ($item) => new OrchidRepository((array) $item)));

        return [
            'rows' => $paginator,
        ];
    }

    public function name(): ?string
    {
        return __('Creators Payout');
    }

    public function description(): ?string
    {
        return __('Withdrawals from creators with linked identity and language.');
    }

    /**
     * Views.
     */
    public function layout(): array
    {
        return [CreatorsPayoutListLayout::class];
    }
}


