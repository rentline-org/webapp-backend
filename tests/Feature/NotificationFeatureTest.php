<?php

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function createNotificationTestContext(): array
{
    $user = User::factory()->create();
    $firstOrganization = Organization::factory()->for($user, 'owner')->create();
    $secondOrganization = Organization::factory()->for($user, 'owner')->create();
    $membership = [
        'role' => OrganizationMemberRole::OWNER->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ];

    $user->organizations()->attach([
        $firstOrganization->id => $membership,
        $secondOrganization->id => $membership,
    ]);
    Sanctum::actingAs($user);

    return [$user, $firstOrganization, $secondOrganization];
}

function createOrganizationNotification(
    User $user,
    Organization $organization,
    string $title,
    ?Carbon $readAt = null,
): DatabaseNotification {
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'tests.operations-digest',
        'data' => [
            'organization_id' => $organization->id,
            'title' => $title,
        ],
        'read_at' => $readAt,
    ]);
}

it('returns only notifications for the active organization and requested read state', function () {
    [$user, $firstOrganization, $secondOrganization] = createNotificationTestContext();
    $activeUnread = createOrganizationNotification($user, $firstOrganization, 'First organization unread');
    $activeRead = createOrganizationNotification($user, $firstOrganization, 'First organization read', now());
    createOrganizationNotification($user, $secondOrganization, 'Second organization unread');

    $this
        ->withHeader('X-Organization-Id', (string) $firstOrganization->id)
        ->getJson('/api/v1/notifications?read=unread')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeUnread->id)
        ->assertJsonPath('data.0.data.organization_id', $firstOrganization->id);

    $this
        ->withHeader('X-Organization-Id', (string) $firstOrganization->id)
        ->getJson('/api/v1/notifications?read=read')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $activeRead->id);
});

it('marks a notification in the active organization as read', function () {
    [$user, $organization] = createNotificationTestContext();
    $notification = createOrganizationNotification($user, $organization, 'Read this notification');

    $this
        ->withHeader('X-Organization-Id', (string) $organization->id)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('returns 404 when marking another organization notification as read', function () {
    [$user, $firstOrganization, $secondOrganization] = createNotificationTestContext();
    $notification = createOrganizationNotification($user, $secondOrganization, 'Private second organization notification');

    $this
        ->withHeader('X-Organization-Id', (string) $firstOrganization->id)
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('marks all notifications read only within the active organization', function () {
    [$user, $firstOrganization, $secondOrganization] = createNotificationTestContext();
    $firstNotification = createOrganizationNotification($user, $firstOrganization, 'First organization notification');
    $secondNotification = createOrganizationNotification($user, $secondOrganization, 'Second organization notification');

    $this
        ->withHeader('X-Organization-Id', (string) $firstOrganization->id)
        ->postJson('/api/v1/notifications/read-all')
        ->assertNoContent();

    expect($firstNotification->refresh()->read_at)->not->toBeNull();
    expect($secondNotification->refresh()->read_at)->toBeNull();
});
