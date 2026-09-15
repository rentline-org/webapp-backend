<?php

use App\Http\Controllers\Api\V1\Document\DocumentController;
use App\Http\Controllers\Api\V1\Document\DocumentFileController;
use App\Http\Controllers\Api\V1\Document\DocumentSignatureController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('documents/{document}/files/{variant}', [DocumentFileController::class, 'show'])
        ->whereIn('variant', ['original', 'signed'])
        ->name('documents.files.download');
    Route::post('documents/{document}/signature', [DocumentSignatureController::class, 'store'])
        ->name('documents.signature.store');
    Route::delete('documents/{document}/signature', [DocumentSignatureController::class, 'destroy'])
        ->name('documents.signature.destroy');
    Route::apiResource('documents', DocumentController::class)->scoped();
});
