<?php

use App\Http\Controllers\Api\V1\ActiveOrganizationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/health', HealthCheckJsonResultsController::class);

// Route::middleware(['auth:sanctum'])->post('select-organization/{organization}', [ActiveOrganizationController::class, 'store']);
Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::post('select-organization/{organization}', [ActiveOrganizationController::class, 'store']);
});

// include 'api/v1/AuthRoutes.php';
// Permission Routes
include 'api/v1/PermissionRoutes.php';
// Role Routes include

// User Routes
include 'api/v1/UserRoutes.php';
// Data Processing Job Routes
include 'api/v1/DataProcessingJobRoutes.php';
include 'api/v1/OrganizationRoutes.php';
include 'api/v1/PropertyRoutes.php';
include 'api/v1/UnitRoutes.php';
