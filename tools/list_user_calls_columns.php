<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$tbl = $argv[1] ?? 'user_calls';
if (! Schema::hasTable($tbl)) {
    echo "NO_TABLE:{$tbl}\n";
    exit(0);
}
$cols = Schema::getColumnListing($tbl);
echo json_encode($cols, JSON_PRETTY_PRINT)."\n";


