<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\LegacyApiController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/index.php', [LegacyApiController::class, 'handle']);
Route::match(['get', 'post'], '/index.php/{path}', [LegacyApiController::class, 'handle'])
    ->where('path', '.*');

Route::prefix('v1')->group(function () {
    Route::get('/buyer/suppliers', [ApiController::class, 'suppliers']);
    Route::get('/buyer/products', [ApiController::class, 'products']);
    Route::get('/buyer/offers', [ApiController::class, 'offers']);
    Route::post('/supplier/offers/create', [ApiController::class, 'createSupplierOffer']);
});
