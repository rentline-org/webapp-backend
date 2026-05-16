<?php

use App\Http\Controllers\Api\V1\Listing\ListingController;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/listing', [ListingController::class, 'index']);
    Route::post('/listing', [ListingController::class, 'store']);
    Route::patch('/listing/{listing}', [ListingController::class, 'update']);

});
