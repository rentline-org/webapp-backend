<?php

use App\Http\Controllers\Api\V1\Unit\UnitController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    // Route::get('/properties/{property}/unit', [UnitController::class, 'index']);
    // Route::get('/properties/{property}/unit/{unit}', [UnitController::class, 'show']);
    // Route::post('/properties/{property}/unit', [UnitController::class, 'store']);
    // Route::patch('/properties/{property}/unit/{unit}', [UnitController::class, 'update']);
    // Route::delete('/properties/{property}/unit/{unit}', [UnitController::class, 'destroy']);
    Route::apiResource('properties.units', UnitController::class)->scoped();
    // Add more routes as needed
});
