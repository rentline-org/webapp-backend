<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentShareRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentShareController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    public function store(StoreDocumentShareRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);

        return DocumentResource::make($this->documentService->grantShare(
            $document,
            (int) $request->validated('user_id'),
            $request->validated('contact_id') === null ? null : (int) $request->validated('contact_id'),
            $request->user()->id,
        ));
    }

    public function destroy(Request $request, Document $document, DocumentShare $share): DocumentResource
    {
        Gate::authorize('update', $document);

        return DocumentResource::make($this->documentService->revokeShare(
            $document,
            $share,
            $request->user()->id,
        ));
    }
}
