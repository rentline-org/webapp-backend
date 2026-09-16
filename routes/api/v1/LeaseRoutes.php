<?php

use App\Http\Controllers\Api\V1\Lease\LeaseActivationController;
use App\Http\Controllers\Api\V1\Lease\LeaseAmendmentController;
use App\Http\Controllers\Api\V1\Lease\LeaseCancellationController;
use App\Http\Controllers\Api\V1\Lease\LeaseController;
use App\Http\Controllers\Api\V1\Lease\LeaseRenewalController;
use App\Http\Controllers\Api\V1\Lease\LeaseTerminationController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('leases', LeaseController::class);
    Route::post('leases/{lease}/activation', [LeaseActivationController::class, 'store']);
    Route::post('leases/{lease}/termination', [LeaseTerminationController::class, 'store']);
    Route::post('leases/{lease}/cancellation', [LeaseCancellationController::class, 'store']);
    Route::post('leases/{lease}/renewals', [LeaseRenewalController::class, 'store']);
    Route::post('leases/{lease}/amendments', [LeaseAmendmentController::class, 'store']);
    Route::post('leases/{lease}/amendments/{amendment}/activation', [LeaseAmendmentController::class, 'activate']);
});
