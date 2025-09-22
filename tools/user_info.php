<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$id = $argv[1] ?? null;
if (! $id) {
    echo "USAGE: php tools/user_info.php <user_id>\n";
    exit(1);
}

$cols = ['id','name','gender','sex','language','lang','audio_status','audio','video_status','video','created_at'];
$available = [];
foreach ($cols as $c) {
    if (Schema::hasColumn('users', $c)) {
        $available[] = $c;
    }
}

if (empty($available)) {
    echo "No common user columns found.\n";
    exit(0);
}

$sel = implode(',', array_map(fn($c) => "`$c`", $available));
$row = DB::selectOne("SELECT $sel FROM users WHERE id = ? LIMIT 1", [$id]);
if (! $row) {
    echo "User not found: $id\n";
    exit(0);
}

echo json_encode((array)$row, JSON_PRETTY_PRINT)."\n";


