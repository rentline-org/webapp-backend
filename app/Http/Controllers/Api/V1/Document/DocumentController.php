<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\DTOs\Document\DocumentDTO;
use App\Enums\DocumentLifecycle;
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
            'custom_kind_id' => ['sometimes', 'integer', 'min:1'],
            'lifecycle' => ['sometimes', Rule::enum(DocumentLifecycle::class)],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'unit_id' => ['sometimes', 'integer', 'min:1'],
            'lease_id' => ['sometimes', 'integer', 'min:1'],
            'contact_id' => ['sometimes', 'integer', 'min:1'],
            'party_role' => ['sometimes', 'string', 'max:50'],
            'signature_status' => ['sometimes', Rule::in(['signed', 'pending', 'not_required'])],
            'visibility' => ['sometimes', Rule::in(['shared', 'internal'])],
            'expires_from' => ['sometimes', 'date_format:Y-m-d'],
            'expires_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:expires_from'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]), $request->integer('per_page', 15));

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
                $request->user()->id,
            )
        );
    }

    public function destroy(Document $document)
    {
        Gate::authorize('delete', $document);

        $this->documentService->delete($document, request()->user()->id);

        return response()->noContent();
    }

    public function archive(Request $request, Document $document)
    {
        Gate::authorize('update', $document);

        return DocumentResource::make(
            $this->documentService->archive($document, $request->user()->id)
        );
    }
}
