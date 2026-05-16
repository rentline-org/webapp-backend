<?php

use App\Http\Controllers\Api\V1\Property\PropertyController;
use App\Http\Controllers\Api\V1\Property\PropertyGalleryController;
use App\Http\Controllers\Api\V1\Property\PropertyThumbnailController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('properties', PropertyController::class)->scoped();
    Route::get('properties/slug/{slug}', [PropertyController::class, 'showBySlug'])->name('properties.showBySlug');

    Route::post('properties/{property}/media/thumbnail', [PropertyThumbnailController::class, 'store']);
    Route::delete('properties/{property}/media/thumbnail', [PropertyThumbnailController::class, 'destroy']);

    Route::post('properties/{property}/media/gallery', [PropertyGalleryController::class, 'store']);
    Route::patch('properties/{property}/media/gallery/{media}', [PropertyGalleryController::class, 'update']);
    Route::delete('properties/{property}/media/gallery/{media}', [PropertyGalleryController::class, 'destroy']);
    Route::delete('properties/{property}/media/gallery', [PropertyGalleryController::class, 'clear']);
});
