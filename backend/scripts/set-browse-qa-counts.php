<?php

/**
 * Temporarily deactivate QA Browse orgs so each type has an exact visible count.
 * Usage:
 *   php scripts/set-browse-qa-counts.php company=5 school=10 municipal_hall=11
 *   php scripts/set-browse-qa-counts.php restore
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Organization;

$arg = $argv[1] ?? 'restore';

if ($arg === 'restore') {
    $n = Organization::query()
        ->where('name', 'like', 'QA Browse %')
        ->update(['is_active' => true]);
    echo json_encode(['restored' => $n], JSON_PRETTY_PRINT), PHP_EOL;
    exit(0);
}

$targets = [];
foreach (array_slice($argv, 1) as $piece) {
    [$type, $count] = array_pad(explode('=', $piece, 2), 2, null);
    if ($type && is_numeric($count)) {
        $targets[$type] = (int) $count;
    }
}

$result = [];
foreach ($targets as $type => $keep) {
    $orgs = Organization::query()
        ->where('name', 'like', 'QA Browse %')
        ->where('type', $type)
        ->orderBy('name')
        ->get();

    foreach ($orgs as $index => $org) {
        $org->is_active = $index < $keep;
        $org->save();
    }
    $result[$type] = [
        'totalQa' => $orgs->count(),
        'active' => min($keep, $orgs->count()),
        'inactive' => max(0, $orgs->count() - $keep),
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
