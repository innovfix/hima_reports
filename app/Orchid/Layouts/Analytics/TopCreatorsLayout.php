<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Analytics;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class TopCreatorsLayout extends Table
{
    /** @var string */
    protected $target = 'rows';

    public function columns(): array
    {
        return [
            TD::make('creator_id', __('Creator ID'))->sort()->width('120px')->align(TD::ALIGN_CENTER),
            TD::make('creator_name', __('Creator Name'))->sort()->width('220px'),
            TD::make('language', __('Language'))->sort()->width('140px'),
            TD::make('audio_status', __('Audio Status'))->sort()->width('140px'),
            TD::make('video_status', __('Video Status'))->sort()->width('140px'),
            TD::make('weekly_audio_calls', __('Calls This Week'))->sort()->width('160px'),
            TD::make('weekly_avg_per_day', __('Avg/Day')).sort()->width('120px'),
        ];
    }
}


