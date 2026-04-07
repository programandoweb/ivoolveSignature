<?php

use App\Http\Controllers\Api\V1\SignatureController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/validate/{document}', [SignatureController::class, 'validateDocument'])
        ->name('signatures.validate');

    Route::middleware('api.key')->group(function (): void {
        Route::post('/signatures/initiate', [SignatureController::class, 'initiate'])
            ->name('signatures.initiate');
        Route::post('/signatures/verify', [SignatureController::class, 'verify'])
            ->name('signatures.verify');
    });
});
