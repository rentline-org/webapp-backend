<?php

use App\Http\Controllers\Api\V1\CustomListing\CustomListingController;
use App\Http\Controllers\Api\V1\Listing\ListingController;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('/listing', [ListingController::class, 'index']);
    Route::post('/listing', [ListingController::class, 'store']);
    Route::patch('/listing/{listing}', [ListingController::class, 'update']);

    Route::post('/listing/{listing}/custom-listing', [CustomListingController::class, 'store']);

    Route::get('/custom-listing/{customListing}', [CustomListingController::class, 'show']);
    Route::patch('/custom-listing/{customListing}', [CustomListingController::class, 'update']);
    Route::patch('/custom-listing/{customListing}/properties', [CustomListingController::class, 'updateProperties']);
    Route::patch('/custom-listing/{customListing}/publish', [CustomListingController::class, 'publish']);
    Route::delete('/custom-listing/{customListing}', [CustomListingController::class, 'destroy']);
});

Route::group(['prefix' => 'v1'], function () {
    Route::get('/website/{subdomain}', [CustomListingController::class, 'showPublic']);
});
