<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentSignatureRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document;
use App\Services\Document\DocumentService;
use Illuminate\Support\Facades\Gate;

class DocumentSignatureController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
    ) {}

    public function store(StoreDocumentSignatureRequest $request, Document $document)
    {
        Gate::authorize('update', $document);

        return DocumentResource::make(
            $this->documentService->markSigned(
                $document,
                $request->file('signed_file'),
                $request->user()->id,
            )
        );
    }

    public function destroy(\Illuminate\Http\Request $request, Document $document)
    {
        Gate::authorize('update', $document);

        return DocumentResource::make(
            $this->documentService->markUnsigned($document, $request->user()->id)
        );
    }
}
