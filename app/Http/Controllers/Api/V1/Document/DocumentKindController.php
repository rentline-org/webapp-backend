<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\DocumentKindRequest;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Services\Document\DocumentKindCatalog;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentKindController extends Controller
{
    public function __construct(
        private readonly DocumentKindCatalog $catalog,
        private readonly ActiveOrganizationContext $activeOrganizationContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Document::class);

        return response()->json(['data' => $this->catalog->all()->all()]);
    }

    public function store(DocumentKindRequest $request): JsonResponse
    {
        $kind = DocumentKind::query()->create([
            ...$request->validated(),
            'organization_id' => $this->activeOrganizationContext->id(),
        ]);

        return response()->json([
            'data' => $this->catalog->payloadFor(\App\Enums\DocumentType::CUSTOM, $kind),
        ], 201);
    }

    public function update(DocumentKindRequest $request, DocumentKind $documentKind): JsonResponse
    {
        $documentKind->update($request->validated());

        return response()->json([
            'data' => $this->catalog->payloadFor(\App\Enums\DocumentType::CUSTOM, $documentKind->refresh()),
        ]);
    }

    public function destroy(DocumentKind $documentKind): JsonResponse
    {
        Gate::authorize('create', Document::class);
        $documentKind->update(['is_active' => false]);

        return response()->json(status: 204);
    }
}
