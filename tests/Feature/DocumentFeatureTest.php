<?php

use App\Enums\ContactPersonType;
use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function createDocumentTestOrganization(UserRole $role = UserRole::LANDLORD): array
{
    $sequence = User::query()->count() + 1;
    $user = User::query()->create([
        'name' => "Document Test User {$sequence}",
        'first_name' => 'Document',
        'last_name' => 'Tester',
        'email' => "document-test-{$sequence}@example.com",
        'phone' => "+55119999{$sequence}",
        'password' => 'password',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    $organization = Organization::factory()->create([
        'owner_id' => $user->id,
        'currency' => 'BRL',
    ]);
    $user->organizations()->attach($organization);

    return [$user, $organization];
}

function createDocumentTestProperty(Organization $organization, string $title = 'Managed Property'): array
{
    $property = Property::query()->create([
        'organization_id' => $organization->id,
        'slug' => str($title)->slug()->toString(),
        'title' => $title,
        'description' => null,
        'address' => '123 Main Street',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'postal_code' => '01000-000',
        'country' => 'BR',
        'property_type' => PropertyType::MULTI_UNIT,
    ]);
    $unit = Unit::query()->create([
        'property_id' => $property->id,
        'name' => 'Unit 204',
        'unit_type' => UnitType::APARTMENT,
    ]);

    return [$property, $unit];
}

function createDocumentTestTenant(Organization $organization, string $name = 'Taylor Tenant'): Contact
{
    return Contact::factory()->tenant()->for($organization)->create([
        'name' => $name,
        'email' => 'tenant-'.$organization->id.'@example.com',
    ]);
}

function useDocumentOrganization(User $user, Organization $organization): array
{
    Sanctum::actingAs($user);

    return [
        'Accept' => 'application/json',
        'X-Organization-Id' => (string) $organization->id,
    ];
}

function fakePdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

function attachDocumentOriginal(Document $document): void
{
    $document->addMedia(fakePdf())
        ->toMediaCollection(MediaCollection::DOCUMENT_ORIGINAL->value);
}

function createLeaseForDocument(
    Document $document,
    Contact $tenant,
    Property $property,
    Unit $unit,
): Lease {
    return $document->lease()->create([
        'tenant_contact_id' => $tenant->id,
        'property_title_snapshot' => $property->title,
        'unit_name_snapshot' => $unit->name,
        'tenant_name_snapshot' => $tenant->name,
        'tenant_email_snapshot' => $tenant->email,
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
        'rent_amount' => 2500,
        'currency' => 'BRL',
        'security_deposit' => 5000,
        'notes' => 'Annual residential lease.',
    ]);
}

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/v1/documents')->assertUnauthorized();
});

it('returns 403 when no active organization is selected', function () {
    [$user] = createDocumentTestOrganization();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/documents')->assertForbidden();
});

it('creates a private generic document for organization safekeeping', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();

    $response = $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Insurance policy',
            'purpose' => 'Organization safekeeping',
            'description' => 'Current liability policy.',
            'requires_signature' => false,
            'is_signed' => false,
            'file' => fakePdf('insurance-policy.pdf'),
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.type', 'generic')
        ->assertJsonPath('data.signature_status', 'not_required')
        ->assertJsonPath('data.files.original.file_name', 'insurance-policy.pdf')
        ->assertJsonPath('data.files.signed', null)
        ->assertJsonMissingPath('data.media')
        ->assertJsonMissingPath('data.files.original.disk');

    $document = Document::withoutGlobalScopes()->findOrFail($response->json('data.id'));
    $media = $document->getFirstMedia(MediaCollection::DOCUMENT_ORIGINAL->value);

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('documents')
        ->and($media->getCustomProperty('source'))->toBe('manual')
        ->and($media->getCustomProperty('sha256'))->toHaveLength(64);
    Storage::disk('documents')->assertExists($media->getPathRelativeToRoot());
});

it('creates a lease document with structured tenant and financial details', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    $tenant = createDocumentTestTenant($organization);

    $response = $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', [
            'type' => DocumentType::LEASE->value,
            'title' => 'Unit 204 lease',
            'purpose' => 'Residential tenancy agreement',
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'requires_signature' => true,
            'is_signed' => false,
            'file' => fakePdf('unit-204-lease.pdf'),
            'lease' => [
                'tenant_contact_id' => $tenant->id,
                'starts_on' => '2026-09-01',
                'ends_on' => '2027-08-31',
                'rent_amount' => '2500.00',
                'security_deposit' => '5000.00',
                'notes' => 'Annual residential lease.',
            ],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.signature_status', 'pending')
        ->assertJsonPath('data.property.id', $property->id)
        ->assertJsonPath('data.unit.id', $unit->id)
        ->assertJsonPath('data.lease.tenant.id', $tenant->id)
        ->assertJsonPath('data.lease.rent_amount', 2500)
        ->assertJsonPath('data.lease.currency', 'BRL');

    $this->assertDatabaseHas('leases', [
        'document_id' => $response->json('data.id'),
        'tenant_contact_id' => $tenant->id,
        'property_title_snapshot' => $property->title,
        'unit_name_snapshot' => $unit->name,
        'tenant_name_snapshot' => $tenant->name,
        'currency' => 'BRL',
    ]);
});

it('rejects signed state without a signed copy', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Signed disclosure',
            'purpose' => 'Tenant disclosure',
            'requires_signature' => true,
            'is_signed' => true,
            'file' => fakePdf('disclosure.pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('signed_file');

    $this->assertDatabaseMissing('documents', ['title' => 'Signed disclosure']);
});

it('rejects cross-organization lease references and mismatched units', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization, 'Primary Property');
    [, $secondUnit] = createDocumentTestProperty($organization, 'Second Property');
    [, $otherOrganization] = createDocumentTestOrganization();
    [$externalProperty, $externalUnit] = createDocumentTestProperty($otherOrganization, 'External Property');
    $externalTenant = createDocumentTestTenant($otherOrganization);

    $payload = [
        'type' => DocumentType::LEASE->value,
        'title' => 'Invalid lease',
        'purpose' => 'Residential tenancy agreement',
        'property_id' => $externalProperty->id,
        'unit_id' => $externalUnit->id,
        'file' => fakePdf(),
        'lease' => [
            'tenant_contact_id' => $externalTenant->id,
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-08-31',
            'rent_amount' => 2500,
        ],
    ];

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['property_id', 'lease.tenant_contact_id']);

    $payload['property_id'] = $property->id;
    $payload['unit_id'] = $secondUnit->id;
    $payload['lease']['tenant_contact_id'] = createDocumentTestTenant($organization)->id;
    $payload['file'] = fakePdf();

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');

    expect($unit->property_id)->toBe($property->id);
});

it('uploads and removes a signed copy through an explicit transition', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    $document = Document::factory()
        ->requiresSignature()
        ->for($organization)
        ->create(['uploaded_by' => $user->id]);
    attachDocumentOriginal($document);
    $headers = useDocumentOrganization($user, $organization);

    $response = $this
        ->withHeaders($headers)
        ->post("/api/v1/documents/{$document->id}/signature", [
            'signed_file' => fakePdf('signed-agreement.pdf'),
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.is_signed', true)
        ->assertJsonPath('data.signature_status', 'signed')
        ->assertJsonPath('data.signed_by', $user->id)
        ->assertJsonPath('data.files.signed.file_name', 'signed-agreement.pdf');

    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'is_signed' => true,
        'signed_by' => $user->id,
    ]);
    $this->assertDatabaseHas('media', [
        'model_type' => Document::class,
        'model_id' => $document->id,
        'collection_name' => MediaCollection::DOCUMENT_SIGNED->value,
    ]);

    $this
        ->withHeaders($headers)
        ->post("/api/v1/documents/{$document->id}/signature", [
            'signed_file' => fakePdf('replacement.pdf'),
        ])
        ->assertConflict();

    $downloadResponse = $this
        ->withHeaders($headers)
        ->get("/api/v1/documents/{$document->id}/files/signed");

    $downloadResponse
        ->assertOk()
        ->assertHeader('pragma', 'no-cache');
    expect($downloadResponse->headers->get('cache-control'))
        ->toContain('private')
        ->toContain('no-store');

    $this
        ->withHeaders($headers)
        ->deleteJson("/api/v1/documents/{$document->id}/signature")
        ->assertOk()
        ->assertJsonPath('data.is_signed', false)
        ->assertJsonPath('data.files.signed', null);

    $this->assertDatabaseMissing('media', [
        'model_type' => Document::class,
        'model_id' => $document->id,
        'collection_name' => MediaCollection::DOCUMENT_SIGNED->value,
    ]);
});

it('updates document metadata but rejects file and signature changes on the general endpoint', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    $document = Document::factory()->for($organization)->create([
        'uploaded_by' => $user->id,
    ]);
    attachDocumentOriginal($document);
    $headers = useDocumentOrganization($user, $organization);

    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/documents/{$document->id}", [
            'title' => 'Updated insurance policy',
            'purpose' => 'Coverage for Unit 204',
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'requires_signature' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated insurance policy')
        ->assertJsonPath('data.property.id', $property->id)
        ->assertJsonPath('data.unit.id', $unit->id)
        ->assertJsonPath('data.signature_status', 'pending');

    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/documents/{$document->id}", [
            'is_signed' => true,
            'file' => 'replacement.pdf',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_signed', 'file']);
});

it('lists only filtered documents from the active organization', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [, $otherOrganization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    [$otherProperty, $otherUnit] = createDocumentTestProperty($otherOrganization, 'Other Organization Property');
    $tenant = createDocumentTestTenant($organization);
    $otherTenant = createDocumentTestTenant($otherOrganization);

    $matching = Document::factory()->lease()->for($organization)->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'title' => 'Oak Street lease',
    ]);
    createLeaseForDocument($matching, $tenant, $property, $unit);

    Document::factory()->for($organization)->create([
        'property_id' => $property->id,
        'title' => 'Oak Street insurance',
    ]);

    $external = Document::factory()->lease()->for($otherOrganization)->create([
        'property_id' => $otherProperty->id,
        'unit_id' => $otherUnit->id,
        'title' => 'Oak Street external lease',
    ]);
    createLeaseForDocument($external, $otherTenant, $otherProperty, $otherUnit);

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->getJson('/api/v1/documents?search=oak&type=lease&signature_status=pending&property_id='.$property->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonMissing(['id' => $external->id]);
});

it('returns 404 for documents in another organization', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [, $otherOrganization] = createDocumentTestOrganization();
    $external = Document::factory()->for($otherOrganization)->create();

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->getJson("/api/v1/documents/{$external->id}")
        ->assertNotFound();
});

it('returns 403 when a tenant user tries to manage documents', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization(UserRole::TENANT);

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->getJson('/api/v1/documents')
        ->assertForbidden();
});

it('preserves lease identity snapshots when the contact is deleted', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    $tenant = createDocumentTestTenant($organization, 'Archived Tenant');
    $document = Document::factory()->lease()->for($organization)->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'uploaded_by' => $user->id,
    ]);
    createLeaseForDocument($document, $tenant, $property, $unit);

    $tenant->delete();

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->getJson("/api/v1/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('data.lease.tenant.id', null)
        ->assertJsonPath('data.lease.tenant.name', 'Archived Tenant');
});

it('deletes a document, its lease extension, and its files', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    $tenant = createDocumentTestTenant($organization);
    $document = Document::factory()->lease()->for($organization)->create([
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'uploaded_by' => $user->id,
    ]);
    createLeaseForDocument($document, $tenant, $property, $unit);
    attachDocumentOriginal($document);
    $media = $document->getFirstMedia(MediaCollection::DOCUMENT_ORIGINAL->value);
    $relativePath = $media->getPathRelativeToRoot();

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('leases', ['document_id' => $document->id]);
    $this->assertDatabaseMissing('media', ['id' => $media->id]);
    Storage::disk('documents')->assertMissing($relativePath);
});
