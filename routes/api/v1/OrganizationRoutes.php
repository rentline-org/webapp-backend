<?php

use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::get('/organization/{id}', [OrganizationController::class, 'show']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::patch('/organization/{id}', [OrganizationController::class, 'update']);
    Route::delete('/organization/{id}', [OrganizationController::class, 'destroy']);
    // Add more routes as needed
});
