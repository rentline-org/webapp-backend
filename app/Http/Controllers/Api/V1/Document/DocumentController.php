<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\DTOs\Document\DocumentDTO;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\DocumentInsertUpdateRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document;
use App\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Document Management
 * APIs for organization documents, generic files, and lease records.
 */
class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Document::class);

        $documents = $this->documentService->all($request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(DocumentType::class)],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'unit_id' => ['sometimes', 'integer', 'min:1'],
            'signature_status' => ['sometimes', Rule::in(['signed', 'pending', 'not_required'])],
        ]));

        return DocumentResource::collection($documents);
    }

    public function store(DocumentInsertUpdateRequest $request)
    {
        Gate::authorize('create', Document::class);

        return DocumentResource::make(
            $this->documentService->create(
                DocumentDTO::fromRequest($request),
                $request->user()->id,
            )
        );
    }

    public function show(Document $document)
    {
        Gate::authorize('view', $document);

        return DocumentResource::make($this->documentService->load($document));
    }

    public function update(DocumentInsertUpdateRequest $request, Document $document)
    {
        Gate::authorize('update', $document);

        return DocumentResource::make(
            $this->documentService->update(
                $document,
                DocumentDTO::fromRequest($request),
            )
        );
    }

    public function destroy(Document $document)
    {
        Gate::authorize('delete', $document);

        $this->documentService->delete($document);

        return response()->noContent();
    }
}
