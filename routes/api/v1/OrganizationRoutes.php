<?php

use App\Http\Controllers\Api\V1\Organization\OrganizationController;
use App\Http\Controllers\Api\V1\Organization\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\Organization\OrganizationLogoController;
use App\Http\Controllers\Api\V1\Organization\OrganizationMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:20,1')->group(function () {
    Route::get('invitations/{token}', [OrganizationInvitationController::class, 'show']);
    Route::post('invitations/{token}/accept', [OrganizationInvitationController::class, 'accept']);
});

Route::middleware(['auth:sanctum'])
    ->prefix('v1')
    ->group(function () {

        Route::apiResource('organizations', OrganizationController::class);

        Route::prefix('organizations/active')->group(function () {

            // Organization Logo
            Route::put('/logo', [OrganizationLogoController::class, 'update']);
            Route::delete('/logo', [OrganizationLogoController::class, 'delete']);
        });

        Route::get('organization-invitations', [OrganizationInvitationController::class, 'index']);
        Route::post('organization-invitations', [OrganizationInvitationController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::post('organization-invitations/{invitation}/resend', [OrganizationInvitationController::class, 'resend'])
            ->middleware('throttle:20,1');
        Route::delete('organization-invitations/{invitation}', [OrganizationInvitationController::class, 'destroy']);

        Route::get('organization-members', [OrganizationMemberController::class, 'index']);
        Route::patch('organization-members/{member}', [OrganizationMemberController::class, 'update']);
        Route::delete('organization-members/{member}', [OrganizationMemberController::class, 'destroy']);
    });
