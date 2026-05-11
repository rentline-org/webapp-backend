<?php

use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use App\Http\Controllers\Api\V1\Organization\OrganizationLogoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('v1')
    ->group(function () {

        Route::apiResource('organizations', OrganizationController::class);

        Route::prefix('organizations/active')->group(function () {

            // Organization Logo
            Route::put('/logo', [OrganizationLogoController::class, 'update']);
            Route::delete('/logo', [OrganizationLogoController::class, 'delete']);
        });
    });
