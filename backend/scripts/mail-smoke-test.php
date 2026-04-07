<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    Mail::raw('Capstone mail smoke test.', function ($m) {
        $m->to('nobody@example.com')->subject('Mail smoke test');
    });
    fwrite(STDOUT, "OK: message accepted by mail transport.\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
    exit(1);
}
