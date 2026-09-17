<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Enums\DocumentSignerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentSignerRequest;
use App\Http\Requests\Document\UpdateDocumentSignerRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentSignerController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    public function store(StoreDocumentSignerRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);

        return DocumentResource::make($this->documentService->addSigner(
            $document,
            $request->validated(),
            $request->user()->id,
        ));
    }

    public function update(UpdateDocumentSignerRequest $request, Document $document, DocumentSigner $signer): DocumentResource
    {
        Gate::authorize('update', $document);
        abort_unless($signer->document_id === $document->id, 404);

        return DocumentResource::make($this->documentService->updateSigner(
            $document,
            $signer,
            DocumentSignerStatus::from($request->validated('status')),
            $request->user()->id,
        ));
    }

    public function destroy(Request $request, Document $document, DocumentSigner $signer): DocumentResource
    {
        Gate::authorize('update', $document);
        abort_unless($signer->document_id === $document->id, 404);

        return DocumentResource::make($this->documentService->removeSigner(
            $document,
            $signer,
            $request->user()->id,
        ));
    }
}
