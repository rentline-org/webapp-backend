<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentVersionRequest;
use App\Http\Resources\Document\DocumentResource;
use App\Models\Document;
use App\Services\Document\DocumentService;
use Illuminate\Support\Facades\Gate;

class DocumentVersionController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    public function store(StoreDocumentVersionRequest $request, Document $document): DocumentResource
    {
        Gate::authorize('update', $document);
        $validated = $request->validated();

        return DocumentResource::make($this->documentService->createVersion(
            document: $document,
            file: $request->file('file'),
            signedFile: $request->file('signed_file'),
            supportingFiles: array_values($request->file('supporting_files', [])),
            supportingLabels: array_values($validated['supporting_labels'] ?? []),
            supportingPartyVisibility: array_values(array_map(
                fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOL),
                $validated['supporting_party_visible'] ?? [],
            )),
            notes: $validated['notes'] ?? null,
            actorId: $request->user()->id,
        ));
    }
}
