<?php

use App\Http\Controllers\Api\V1\Unit\UnitController;
use App\Http\Controllers\Api\V1\Unit\UnitMediaController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::apiResource('properties.units', UnitController::class)->scoped();

    Route::post('properties/{property}/units/{unit}/media/gallery', [UnitMediaController::class, 'storeGallery']);
    Route::post('properties/{property}/units/{unit}/media/thumbnail', [UnitMediaController::class, 'storeThumbnail']);

    Route::delete('properties/{property}/units/{unit}/media/gallery/{media}', [UnitMediaController::class, 'destroyGalleryImage']);
    Route::delete('properties/{property}/units/{unit}/media/thumbnail', [UnitMediaController::class, 'destroyThumbnail']);
    Route::delete('properties/{property}/units/{unit}/media/gallery', [UnitMediaController::class, 'clearGallery']);

});
