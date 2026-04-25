<?php

use App\Http\Controllers\LegacyApiController;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
$app->instance('request', $request);

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$response = $app->make(LegacyApiController::class)->handle($request);

$response->send();
$kernel->terminate($request, $response);
