<?php

use App\Http\Controllers\Api\V1\Contact\ContactController;
use App\Http\Controllers\Api\V1\Contact\ContactAssignmentController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('contacts', ContactController::class)->scoped();
    Route::apiResource('contacts.assignments', ContactAssignmentController::class)->scoped();
});
