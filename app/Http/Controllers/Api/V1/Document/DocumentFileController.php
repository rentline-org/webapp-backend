<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Enums\MediaCollection;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function show(Request $request, Document $document, string $variant): StreamedResponse
    {
        Gate::authorize('view', $document);

        $collection = match ($variant) {
            'original' => MediaCollection::DOCUMENT_ORIGINAL->value,
            'signed' => MediaCollection::DOCUMENT_SIGNED->value,
        };
        $media = $document->getFirstMedia($collection);

        abort_if($media === null, 404);

        $response = $media->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
