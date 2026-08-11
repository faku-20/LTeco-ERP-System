<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

Route::get('/health/live',[HealthController::class,'live'])->middleware('throttle:60,1');
Route::get('/health/ready',[HealthController::class,'ready'])->middleware('throttle:30,1');

Route::middleware('service.hmac')
    ->prefix('internal/v1')
    ->group(function (): void {
        Route::get('/ping', function (Request $request) {
            return response()->json([
                'data' => [
                    'status' => 'ok',
                    'correlation_id' => $request->attributes->get('correlation_id'),
                ],
            ]);
        })->name('internal.ping');

        Route::post('/panel-events', function () {
            return response()->json([
                'data' => ['status' => 'accepted'],
            ], 202);
        })->name('internal.panel-events.store');
    });
