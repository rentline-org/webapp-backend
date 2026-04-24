<?php

use App\Http\Controllers\Api\V1\Contact\ContactController;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/contact', [ContactController::class, 'index']);
    Route::get('/contact/{id}', [ContactController::class, 'show']);
    Route::post('/contact', [ContactController::class, 'store']);
    Route::patch('/contact/{id}', [ContactController::class, 'update']);
    Route::delete('/contact/{id}', [ContactController::class, 'destroy']);
    // Add more routes as needed
});
