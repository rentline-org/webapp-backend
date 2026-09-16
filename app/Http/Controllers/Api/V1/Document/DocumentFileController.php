<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Enums\MediaCollection;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Media;
use App\Services\Document\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    public function show(Request $request, Document $document, string $variant): StreamedResponse
    {
        Gate::authorize('view', $document);
        $version = $document->versions()->orderByDesc('version_number')->first();
        $media = match ($variant) {
            'original' => $version?->primaryMedia ?? $document->getLastMedia(MediaCollection::DOCUMENT_ORIGINAL->value),
            'signed' => $version?->signedMedia ?? $document->getLastMedia(MediaCollection::DOCUMENT_SIGNED->value),
        };

        return $this->download($request, $document, $media, $variant, $version);
    }

    public function showVersion(
        Request $request,
        Document $document,
        DocumentVersion $version,
        string $variant,
    ): StreamedResponse {
        Gate::authorize('view', $document);
        abort_unless($version->document_id === $document->id, 404);
        $media = match ($variant) {
            'original' => $version->primaryMedia,
            'signed' => $version->signedMedia,
        };

        return $this->download($request, $document, $media, $variant, $version);
    }

    public function showSupporting(
        Request $request,
        Document $document,
        DocumentVersion $version,
        Media $media,
    ): StreamedResponse {
        Gate::authorize('view', $document);
        abort_unless(
            $version->document_id === $document->id
            && $media->model_type === Document::class
            && $media->model_id === $document->id
            && $media->collection_name === MediaCollection::DOCUMENT_SUPPORTING->value
            && (int) $media->getCustomProperty('version_id') === $version->id,
            404,
        );
        abort_unless(
            Gate::allows('update', $document) || (bool) $media->getCustomProperty('party_visible', false),
            403,
        );

        return $this->download($request, $document, $media, 'supporting', $version);
    }

    private function download(
        Request $request,
        Document $document,
        ?Media $media,
        string $variant,
        ?DocumentVersion $version,
    ): StreamedResponse {
        abort_if($media === null, 404);
        $this->documentService->audit($document, 'document.file_downloaded', $request->user()?->id, [
            'media_id' => $media->id,
            'variant' => $variant,
            'version' => $version?->version_number,
        ]);

        $response = $media->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
