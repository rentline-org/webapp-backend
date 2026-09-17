<?php

use App\Http\Controllers\Api\V1\Document\DocumentAuditEventController;
use App\Http\Controllers\Api\V1\Document\DocumentController;
use App\Http\Controllers\Api\V1\Document\DocumentFileController;
use App\Http\Controllers\Api\V1\Document\DocumentKindController;
use App\Http\Controllers\Api\V1\Document\DocumentShareController;
use App\Http\Controllers\Api\V1\Document\DocumentSignatureController;
use App\Http\Controllers\Api\V1\Document\DocumentSignerController;
use App\Http\Controllers\Api\V1\Document\DocumentVersionController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum']], function () {
    Route::get('document-kinds', [DocumentKindController::class, 'index'])->name('document-kinds.index');
    Route::post('document-kinds', [DocumentKindController::class, 'store'])->name('document-kinds.store');
    Route::patch('document-kinds/{documentKind}', [DocumentKindController::class, 'update'])->name('document-kinds.update');
    Route::delete('document-kinds/{documentKind}', [DocumentKindController::class, 'destroy'])->name('document-kinds.destroy');
    Route::get('documents/{document}/files/{variant}', [DocumentFileController::class, 'show'])
        ->whereIn('variant', ['original', 'signed'])
        ->name('documents.files.download');
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->name('documents.versions.store');
    Route::get('documents/{document}/versions/{version}/files/{variant}', [DocumentFileController::class, 'showVersion'])
        ->whereIn('variant', ['original', 'signed'])
        ->name('documents.versions.files.download');
    Route::get('documents/{document}/versions/{version}/supporting/{media}', [DocumentFileController::class, 'showSupporting'])
        ->name('documents.versions.supporting.download');
    Route::post('documents/{document}/signature', [DocumentSignatureController::class, 'store'])
        ->name('documents.signature.store');
    Route::delete('documents/{document}/signature', [DocumentSignatureController::class, 'destroy'])
        ->name('documents.signature.destroy');
    Route::post('documents/{document}/signers', [DocumentSignerController::class, 'store'])->name('documents.signers.store');
    Route::patch('documents/{document}/signers/{signer}', [DocumentSignerController::class, 'update'])->name('documents.signers.update');
    Route::delete('documents/{document}/signers/{signer}', [DocumentSignerController::class, 'destroy'])->name('documents.signers.destroy');
    Route::post('documents/{document}/shares', [DocumentShareController::class, 'store'])->name('documents.shares.store');
    Route::delete('documents/{document}/shares/{share}', [DocumentShareController::class, 'destroy'])->name('documents.shares.destroy');
    Route::get('documents/{document}/audit-events', [DocumentAuditEventController::class, 'index'])->name('documents.audit-events.index');
    Route::post('documents/{document}/activate', [DocumentController::class, 'activate'])->name('documents.activate');
    Route::post('documents/{document}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::apiResource('documents', DocumentController::class)->scoped();
});
