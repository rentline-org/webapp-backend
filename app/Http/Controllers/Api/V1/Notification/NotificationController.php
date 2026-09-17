<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        abort_unless($organizationId !== null, Response::HTTP_FORBIDDEN);
        $validated = $request->validate([
            'read' => ['sometimes', Rule::in(['read', 'unread'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $notifications = $request->user()
            ->notifications()
            ->where('data->organization_id', $organizationId)
            ->when(
                ($validated['read'] ?? null) === 'read',
                fn ($query) => $query->whereNotNull('read_at')
            )
            ->when(
                ($validated['read'] ?? null) === 'unread',
                fn ($query) => $query->whereNull('read_at')
            )
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return response()->json($notifications);
    }

    public function read(Request $request, string $notification)
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        abort_unless($organizationId !== null, Response::HTTP_FORBIDDEN);
        $record = $request->user()
            ->notifications()
            ->where('data->organization_id', $organizationId)
            ->whereKey($notification)
            ->firstOrFail();
        $record->markAsRead();

        return response()->json(['data' => ['read_at' => $record->read_at]], Response::HTTP_OK);
    }

    public function readAll(Request $request)
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        abort_unless($organizationId !== null, Response::HTTP_FORBIDDEN);
        $request->user()
            ->unreadNotifications()
            ->where('data->organization_id', $organizationId)
            ->update(['read_at' => now()]);

        return $this->successNoContent();
    }
}
