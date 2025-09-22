<?php
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

// detect creator id column
$possibleCreatorCols = ['call_user_id','creator_id','receiver_id','callee_id','to_user_id','to_id','called_user_id','provider_id','user_id'];
$creatorCol = null;
foreach ($possibleCreatorCols as $c) {
    if (Schema::hasColumn($found, $c)) { $creatorCol = $c; break; }
}

if (! $creatorCol) {
    echo "NO_CREATOR_ID_COLUMN_FOUND_IN_{$found}\n";
    exit(0);
}

echo "TABLE={$found} CREATOR_COL={$creatorCol}\n";
// detect timestamp column
$possibleTsCols = ['created_at','started_at','start_time','call_time','began_at','createdOn'];
$timestampCol = null;
foreach ($possibleTsCols as $c) { if (Schema::hasColumn($found, $c)) { $timestampCol = $c; break; } }

if ($start && $end) {
    echo "PERIOD={$period} START={$start} END={$end}\n";
    $sql = "SELECT u.id as user_id, u.name, LOWER(u.gender) as gender, COUNT(c.id) as cnt
            FROM {$found} c
            JOIN users u ON u.id = c.{$creatorCol}
            WHERE c.{$timestampCol} BETWEEN ? AND ?
              AND LOWER(c.type) IN ('audio','voice','audio_call')
              AND LOWER(u.gender) IN ('female','f')
            GROUP BY u.id, u.name, LOWER(u.gender)
            ORDER BY cnt DESC
            LIMIT 50";
    $rows = DB::select($sql, [$start, $end]);
} else {
    echo "PERIOD=all\n";
    $sql = "SELECT u.id as user_id, u.name, LOWER(u.gender) as gender, COUNT(c.id) as cnt
            FROM {$found} c
            JOIN users u ON u.id = c.{$creatorCol}
            WHERE LOWER(c.type) IN ('audio','voice','audio_call')
              AND LOWER(u.gender) IN ('female','f')
            GROUP BY u.id, u.name, LOWER(u.gender)
            ORDER BY cnt DESC
            LIMIT 50";
    $rows = DB::select($sql);
}

echo json_encode($rows, JSON_PRETTY_PRINT)."\n";


