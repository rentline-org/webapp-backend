<?php

use App\Http\Controllers\Api\V1\Property\PropertyController;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/property', [PropertyController::class, 'index']);
    Route::get('/property/{id}', [PropertyController::class, 'show']);
    Route::post('/property', [PropertyController::class, 'store']);
    Route::patch('/property/{id}', [PropertyController::class, 'update']);
    Route::delete('/property/{id}', [PropertyController::class, 'destroy']);
    // Add more routes as needed
});
