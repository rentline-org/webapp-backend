<?php

use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])
    ->prefix('v1')
    ->group(function () {

        Route::apiResource('organizations', OrganizationController::class);

        Route::prefix('organizations/active')->group(function () {
            Route::put('/logo', [OrganizationController::class, 'updateLogo']);
            Route::delete('/logo', [OrganizationController::class, 'deleteLogo']);
        });
    });
