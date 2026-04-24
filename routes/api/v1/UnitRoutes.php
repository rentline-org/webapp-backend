<?php

use App\Http\Controllers\Api\V1\Unit\UnitController;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/unit', [UnitController::class, 'index']);
    Route::get('/unit/{id}', [UnitController::class, 'show']);
    Route::post('/unit', [UnitController::class, 'store']);
    Route::patch('/unit/{id}', [UnitController::class, 'update']);
    Route::delete('/unit/{id}', [UnitController::class, 'destroy']);
    // Add more routes as needed
});
