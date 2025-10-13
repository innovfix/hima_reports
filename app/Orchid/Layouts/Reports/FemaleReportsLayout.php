<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Reports;

use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class FemaleReportsLayout extends Table
{
    /** @var string */
    protected $target = 'rows';

    public function columns(): array
    {
        return [
            TD::make('creator_id', __('Creator ID'))
                ->sort()
                ->width('120px')
                ->align(TD::ALIGN_CENTER),

            TD::make('creator_name', __('Creator Name'))
                ->sort()
                ->width('220px')
                ->align(TD::ALIGN_LEFT),

            TD::make('language', __('Language'))
                ->sort()
                ->width('140px')
                ->render(function ($row) {
                    $val = null;
                    try { 
                        $val = $row->getContent('language'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['language'] ?? null) : null; 
                    }
                    
                    if (empty($val)) {
                        return '-';
                    }
                    
                    // Deterministic color per language — special-case Kannada to brown
                    $lower = strtolower(trim((string)$val));
                    if ($lower === 'kannada') {
                        $hex = 'a52a2a'; // brown
                    } else {
                        $hex = substr(md5($lower), 0, 6);
                    }
                    
                    return "<span class='language-badge' style='background:#{$hex};color:#fff;padding:4px 8px;border-radius:12px;display:inline-block;'>".htmlspecialchars((string)$val)."</span>";
                }),

            TD::make('total_calls', __('Total Calls'))
                ->sort()
                ->width('120px')
                ->align(TD::ALIGN_CENTER)
                ->render(function ($row) {
                    try { 
                        $val = $row->getContent('total_calls'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['total_calls'] ?? 0) : 0; 
                    }
                    return (string)($val ?? 0);
                }),

            TD::make('audio_calls', __('Audio Calls'))
                ->sort()
                ->width('120px')
                ->align(TD::ALIGN_CENTER)
                ->render(function ($row) {
                    try { 
                        $val = $row->getContent('audio_calls'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['audio_calls'] ?? 0) : 0; 
                    }
                    return (string)($val ?? 0);
                }),

            TD::make('audio_call_duration', __('Audio Duration'))
                ->sort()
                ->width('220px')
                ->align(TD::ALIGN_LEFT)
                ->render(function ($row) {
                    try { 
                        $val = $row->getContent('audio_call_duration'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['audio_call_duration'] ?? null) : null; 
                    }
                    
                    if ($val === null || $val == 0) {
                        return '<span class="text-muted">0 hours 0 minutes 0 seconds</span>';
                    }
                    
                    // Convert minutes to hours:minutes:seconds
                    $totalMinutes = (float)$val;
                    $hours = floor($totalMinutes / 60);
                    $minutes = floor($totalMinutes % 60);
                    $seconds = floor(($totalMinutes - floor($totalMinutes)) * 60);
                    
                    $parts = [];
                    if ($hours > 0) {
                        $parts[] = $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
                    }
                    if ($minutes > 0 || $hours > 0) {
                        $parts[] = $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');
                    }
                    $parts[] = $seconds . ' ' . ($seconds == 1 ? 'second' : 'seconds');
                    
                    return '<strong>' . implode(' ', $parts) . '</strong>';
                }),

            TD::make('video_calls', __('Video Calls'))
                ->sort()
                ->width('120px')
                ->align(TD::ALIGN_CENTER)
                ->render(function ($row) {
                    try { 
                        $val = $row->getContent('video_calls'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['video_calls'] ?? 0) : 0; 
                    }
                    return (string)($val ?? 0);
                }),

            TD::make('video_call_duration', __('Video Duration'))
                ->sort()
                ->width('220px')
                ->align(TD::ALIGN_LEFT)
                ->render(function ($row) {
                    try { 
                        $val = $row->getContent('video_call_duration'); 
                    } catch (\Throwable $e) { 
                        $val = is_array($row) ? ($row['video_call_duration'] ?? null) : null; 
                    }
                    
                    if ($val === null || $val == 0) {
                        return '<span class="text-muted">0 hours 0 minutes 0 seconds</span>';
                    }
                    
                    // Convert minutes to hours:minutes:seconds
                    $totalMinutes = (float)$val;
                    $hours = floor($totalMinutes / 60);
                    $minutes = floor($totalMinutes % 60);
                    $seconds = floor(($totalMinutes - floor($totalMinutes)) * 60);
                    
                    $parts = [];
                    if ($hours > 0) {
                        $parts[] = $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
                    }
                    if ($minutes > 0 || $hours > 0) {
                        $parts[] = $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes');
                    }
                    $parts[] = $seconds . ' ' . ($seconds == 1 ? 'second' : 'seconds');
                    
                    return '<strong>' . implode(' ', $parts) . '</strong>';
                }),
        ];
    }
}

