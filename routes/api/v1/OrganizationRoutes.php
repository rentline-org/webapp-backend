<?php

use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/organization', [OrganizationController::class, 'index']);
    Route::get('/organization/{organization}', [OrganizationController::class, 'show']);
    Route::post('/organization', [OrganizationController::class, 'store']);
    Route::patch('/organization/{organization}', [OrganizationController::class, 'update']);
    Route::delete('/organization/{organization}', [OrganizationController::class, 'destroy']);
    // Add more routes as needed
});
