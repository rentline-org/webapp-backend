<?php

use App\Enums\ActionItemStatus;
use App\Enums\ActionItemType;
use App\Enums\DocumentLifecycle;
use App\Enums\DocumentType;
use App\Enums\LeasePartyRole;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\ActionItem;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Lease;
use App\Models\LeaseParty;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\OperationsDigestNotification;
use App\Services\Operations\ActionItemService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function operationalOrganization(): array
{
    $owner = User::factory()->create();
    $organization = Organization::factory()->create([
        'owner_id' => $owner->id,
        'timezone' => 'America/Sao_Paulo',
        'settings' => ['operations' => ['expiry_alert_days' => [30, 7]]],
    ]);
    $owner->organizations()->attach($organization, [
        'role' => OrganizationMemberRole::OWNER->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);
    $property = Property::factory()->for($organization)->create();
    $unit = Unit::factory()->for($property)->create();

    return [$owner, $organization, $property, $unit];
}

function operationalHeaders(User $user, Organization $organization): array
{
    Sanctum::actingAs($user);

    return [
        'Accept' => 'application/json',
        'X-Organization-Id' => (string) $organization->id,
    ];
}

it('generates lease and document reminders idempotently with the nearest alert window', function () {
    [$owner, $organization, $property, $unit] = operationalOrganization();
    $today = CarbonImmutable::parse('2026-09-16', $organization->timezone)->startOfDay();
    $lease = Lease::withoutGlobalScopes()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'workflow_status' => LeaseWorkflowStatus::ACTIVE,
        'title' => 'Apartment lease',
        'starts_on' => $today->subMonth()->toDateString(),
        'ends_on' => $today->addDays(6)->toDateString(),
        'rent_amount' => '2500.00',
        'currency' => 'BRL',
        'activated_by' => $owner->id,
        'activated_at' => $today,
    ]);
    $document = Document::withoutGlobalScopes()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'uploaded_by' => $owner->id,
        'type' => DocumentType::COMPLIANCE_CERTIFICATE,
        'lifecycle' => DocumentLifecycle::ACTIVE,
        'title' => 'Fire safety certificate',
        'purpose' => 'Property compliance',
        'expires_on' => $today->addDays(20)->toDateString(),
    ]);

    $service = app(ActionItemService::class);
    $firstRun = $service->generateForOrganization($organization, $today);
    $secondRun = $service->generateForOrganization($organization, $today);

    expect($firstRun->pluck('unique_key')->all())->toContain(
        "lease-expiry-{$lease->id}",
        "document-expiry-{$document->id}",
        "missing-move-in-inspection-{$lease->id}",
    )->and($secondRun)->toHaveCount(3)
        ->and(ActionItem::query()->where('organization_id', $organization->id)->count())->toBe(3)
        ->and(ActionItem::query()->where('unique_key', "lease-expiry-{$lease->id}")->value('metadata')['alert_window_days'])->toBe(7)
        ->and(ActionItem::query()->where('unique_key', "document-expiry-{$document->id}")->value('metadata')['alert_window_days'])->toBe(30);
});

it('sends a localized operations digest only once per organization day', function () {
    Notification::fake();
    [$owner, $organization, $property] = operationalOrganization();
    $today = CarbonImmutable::parse('2026-09-16', $organization->timezone)->startOfDay();
    $item = ActionItem::factory()->for($organization)->create([
        'property_id' => $property->id,
        'unique_key' => 'document-expiry-demo',
    ]);
    $items = ActionItem::query()->whereKey($item->id)->get();
    $service = app(ActionItemService::class);

    expect($service->sendDailyDigests($organization, $today, $items))->toBe(1)
        ->and($service->sendDailyDigests($organization, $today, $items))->toBe(0);

    Notification::assertSentToTimes($owner, OperationsDigestNotification::class, 1);
    $this->assertDatabaseCount('operations_digest_deliveries', 1);
});

it('limits tenant action items to their leases and explicitly shared documents', function () {
    [$owner, $organization, $property, $unit] = operationalOrganization();
    $tenant = User::factory()->create();
    $tenant->organizations()->attach($organization, [
        'role' => OrganizationMemberRole::TENANT->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);
    $contact = Contact::factory()->for($organization)->create([
        'user_id' => $tenant->id,
        'email' => $tenant->email,
    ]);
    $lease = Lease::withoutGlobalScopes()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'workflow_status' => LeaseWorkflowStatus::ACTIVE,
        'starts_on' => today()->subMonth(),
        'ends_on' => today()->addMonth(),
        'rent_amount' => '1800.00',
        'currency' => 'BRL',
    ]);
    LeaseParty::query()->create([
        'organization_id' => $organization->id,
        'lease_id' => $lease->id,
        'contact_id' => $contact->id,
        'role' => LeasePartyRole::PRIMARY_TENANT,
        'is_primary' => true,
        'name_snapshot' => $contact->name,
        'email_snapshot' => $contact->email,
    ]);
    $sharedDocument = Document::withoutGlobalScopes()->create([
        'organization_id' => $organization->id,
        'type' => DocumentType::GENERIC,
        'lifecycle' => DocumentLifecycle::ACTIVE,
        'title' => 'Tenant handbook',
        'purpose' => 'Shared information',
    ]);
    DocumentShare::query()->create([
        'document_id' => $sharedDocument->id,
        'contact_id' => $contact->id,
        'user_id' => $tenant->id,
        'granted_by' => $owner->id,
        'granted_at' => now(),
    ]);
    ActionItem::factory()->for($organization)->create([
        'lease_id' => $lease->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'type' => ActionItemType::LEASE_EXPIRY,
        'unique_key' => 'tenant-lease',
    ]);
    ActionItem::factory()->for($organization)->create([
        'document_id' => $sharedDocument->id,
        'type' => ActionItemType::DOCUMENT_EXPIRY,
        'unique_key' => 'tenant-shared-document',
    ]);
    ActionItem::factory()->for($organization)->create([
        'type' => ActionItemType::EXPIRED_COMPLIANCE,
        'unique_key' => 'internal-only',
    ]);

    $this
        ->withHeaders(operationalHeaders($tenant, $organization))
        ->getJson('/api/v1/action-items')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);

    $item = ActionItem::query()->where('unique_key', 'tenant-lease')->firstOrFail();
    $this
        ->withHeaders(operationalHeaders($tenant, $organization))
        ->patchJson("/api/v1/action-items/{$item->id}", ['status' => ActionItemStatus::DISMISSED->value])
        ->assertForbidden();
});
