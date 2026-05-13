<?php

use App\Http\Controllers\Api\V1\Unit\UnitController;
use App\Http\Controllers\Api\V1\Unit\UnitGalleryController;
use App\Http\Controllers\Api\V1\Unit\UnitThumbnailController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('properties.units', UnitController::class)->scoped();

    Route::delete('properties/{property}/units/{unit}/media/thumbnail', [UnitThumbnailController::class, 'destroyThumbnail']);
    Route::post('properties/{property}/units/{unit}/media/thumbnail', [UnitThumbnailController::class, 'storeThumbnail']);

    Route::post('properties/{property}/units/{unit}/media/gallery', [UnitGalleryController::class, 'storeGallery']);
    Route::patch('properties/{property}/units/{unit}/media/gallery/{media}', [UnitGalleryController::class, 'updateGalleryImageName']);
    Route::delete('properties/{property}/units/{unit}/media/gallery/{media}', [UnitGalleryController::class, 'destroyGalleryImage']);
    Route::delete('properties/{property}/units/{unit}/media/gallery', [UnitGalleryController::class, 'clearGallery']);

});
