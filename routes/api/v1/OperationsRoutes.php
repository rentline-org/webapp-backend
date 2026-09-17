<?php

use App\Http\Controllers\Api\V1\ActionItem\ActionItemController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('action-items', [ActionItemController::class, 'index']);
    Route::patch('action-items/{actionItem}', [ActionItemController::class, 'update']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
});
