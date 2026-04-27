<?php

use App\Http\Controllers\Api\V1\Property\PropertyController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('properties', PropertyController::class);
});
