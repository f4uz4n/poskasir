<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$s = app(App\Services\WindowsPrinterService::class);
$ref = new ReflectionClass($s);
$m = $ref->getMethod('writeDriverText');
$m->setAccessible(true);

$start = microtime(true);
$m->invoke($s, 'POS-58', "TEST\r\n".str_repeat('-', 32)."\r\nOK\r\n", 58);
$ms = round((microtime(true) - $start) * 1000);
echo "writeDriverText: {$ms}ms\n";
