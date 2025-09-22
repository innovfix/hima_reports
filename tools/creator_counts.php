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
    echo "USAGE: php tools/creator_counts.php <creator_id> [this_week|last_week|all]\n";
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

$where = "WHERE call_user_id = ? AND LOWER(type) IN ('audio','voice','audio_call')";
$params = [$creator];
if ($start && $end) {
    $where .= " AND created_at BETWEEN ? AND ?";
    $params[] = $start; $params[] = $end;
}

$totalSql = "SELECT COUNT(*) as total FROM user_calls {$where}";
$completedSql = "SELECT COUNT(*) as completed FROM user_calls {$where} AND ended_time IS NOT NULL AND ended_time <> ''";
$secondsSql = "SELECT SUM(CASE WHEN started_time IS NOT NULL AND ended_time IS NOT NULL AND created_at IS NOT NULL THEN GREATEST(0, TIMESTAMPDIFF(SECOND, CONCAT(DATE(created_at),' ',started_time), CONCAT(DATE(created_at),' ',ended_time))) ELSE 0 END) as total_seconds FROM user_calls {$where} AND ended_time IS NOT NULL AND ended_time <> ''";

$total = DB::selectOne($totalSql, $params)->total ?? 0;
$completed = DB::selectOne($completedSql, $params)->completed ?? 0;
$totalSeconds = DB::selectOne($secondsSql, $params)->total_seconds ?? 0;

echo json_encode([
    'creator' => (int)$creator,
    'period' => $period,
    'start' => $start,
    'end' => $end,
    'total_audio_rows' => (int)$total,
    'completed_audio_rows' => (int)$completed,
    'total_seconds' => (int)$totalSeconds,
    'avg_minutes_per_day' => $start && $end ? round($totalSeconds / ((Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1) * 60), 2) : ($totalSeconds ? round($totalSeconds/60,2) : null)
], JSON_PRETTY_PRINT)."\n";


