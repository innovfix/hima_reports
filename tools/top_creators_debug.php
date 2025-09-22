<?php
// Debug script to inspect call counts for TopCreators periods
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

$period = $argv[1] ?? 'this_week';

if ($period === 'last_week') {
    $start = Carbon::now()->subWeek()->startOfWeek()->toDateTimeString();
    $end = Carbon::now()->subWeek()->endOfWeek()->toDateTimeString();
} elseif ($period === 'all') {
    $start = null;
    $end = null;
} else {
    $start = Carbon::now()->startOfWeek()->toDateTimeString();
    $end = Carbon::now()->endOfWeek()->toDateTimeString();
}

$possible = ['user_calls','calls','call_logs','voice_calls','call_history','callhistory'];
$found = null;
foreach ($possible as $t) {
    if (Schema::hasTable($t)) { $found = $t; break; }
}

if (! $found) {
    echo "NO_CALLS_TABLE\n";
    exit(0);
}

// detect creator id column in calls table
$possibleCreatorCols = ['creator_id','receiver_id','callee_id','to_user_id','to_id','called_user_id','provider_id','user_id'];
$creatorCol = null;
foreach ($possibleCreatorCols as $c) {
    if (Schema::hasColumn($found, $c)) { $creatorCol = $c; break; }
}

if (! $creatorCol) {
    echo "NO_CREATOR_ID_COLUMN_FOUND_IN_{$found}\n";
    exit(0);
}

echo "TABLE={$found} CREATOR_COL={$creatorCol}\n";
if ($start && $end) {
    echo "PERIOD={$period} START={$start} END={$end}\n";
    $rows = DB::select("SELECT {$found}.{$creatorCol} as creator_id, COUNT(*) as cnt FROM {$found} WHERE {$found}.{$timestampCol} BETWEEN ? AND ? AND LOWER(type) IN ('audio','voice','audio_call') GROUP BY {$found}.{$creatorCol} ORDER BY cnt DESC LIMIT 50", [$start, $end]);
} else {
    echo "PERIOD=all\n";
    $rows = DB::select("SELECT {$found}.{$creatorCol} as creator_id, COUNT(*) as cnt FROM {$found} WHERE LOWER(type) IN ('audio','voice','audio_call') GROUP BY {$found}.{$creatorCol} ORDER BY cnt DESC LIMIT 50");
}

echo json_encode($rows, JSON_PRETTY_PRINT)."\n";


