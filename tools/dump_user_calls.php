<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$creator = $argv[1] ?? null;
$period = $argv[2] ?? 'last_week';
if (! $creator) {
    echo "USAGE: php tools/dump_user_calls.php <creator_id> [this_week|last_week|all]\n";
    exit(1);
}

if ($period === 'last_week') {
    $start = Carbon::now()->subWeek()->startOfWeek()->toDateTimeString();
    $end = Carbon::now()->subWeek()->endOfWeek()->toDateTimeString();
} elseif ($period === 'this_week') {
    $start = Carbon::now()->startOfWeek()->toDateTimeString();
    $end = Carbon::now()->endOfWeek()->toDateTimeString();
} else {
    $start = null; $end = null;
}

$params = [$creator];
$where = "WHERE (call_user_id = ?)";
if ($start && $end) {
    $where .= " AND (created_at BETWEEN ? AND ?)";
    $params[] = $start; $params[] = $end;
}

$sql = "SELECT id, call_user_id, user_id, type, started_time, ended_time, created_at, datetime, update_current_endedtime FROM user_calls " . $where . " ORDER BY created_at DESC LIMIT 200";
$rows = DB::select($sql, $params);
echo json_encode($rows, JSON_PRETTY_PRINT)."\n";


