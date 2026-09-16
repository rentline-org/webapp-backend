<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Http\Resources\Document\DocumentAuditEventResource;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DocumentAuditEventController extends Controller
{
    public function index(Request $request, Document $document): AnonymousResourceCollection
    {
        Gate::authorize('update', $document);
        $events = $document->auditEvents()
            ->with('actor:id,name')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return DocumentAuditEventResource::collection($events);
    }
}
