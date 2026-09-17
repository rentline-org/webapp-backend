<?php

use App\Enums\DocumentLifecycle;
use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentKind;
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
        'email' => 'tenant-' . $organization->id . '@example.com',
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

it('uploads a signed copy and keeps the completed version immutable', function () {
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
        ->assertConflict();

    $this->assertDatabaseHas('media', [
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
        ->getJson('/api/v1/documents?search=oak&type=lease&signature_status=pending&property_id=' . $property->id)
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

it('returns an empty register to a tenant without shares and forbids document creation', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization(UserRole::TENANT);

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Forbidden tenant upload',
            'purpose' => 'Should not be stored',
            'file' => fakePdf(),
        ])
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

it('returns the bilingual catalog and keeps custom kinds organization scoped', function () {
    [$user, $organization] = createDocumentTestOrganization();
    [, $otherOrganization] = createDocumentTestOrganization();
    $headers = useDocumentOrganization($user, $organization);

    $created = $this
        ->withHeaders($headers)
        ->postJson('/api/v1/document-kinds', [
            'key' => 'utility_statement',
            'label_en' => 'Utility statement',
            'label_pt_br' => 'Conta de consumo',
            'category' => 'other',
            'allowed_scopes' => ['property'],
            'supports_expiry' => false,
            'default_requires_signature' => false,
        ]);

    $created
        ->assertCreated()
        ->assertJsonPath('data.key', 'custom:utility_statement')
        ->assertJsonPath('data.required_parties', []);

    $kindId = $created->json('data.custom_kind_id');

    $this
        ->withHeaders([...$headers, 'Accept-Language' => 'pt-BR'])
        ->getJson('/api/v1/document-kinds')
        ->assertOk()
        ->assertJsonFragment([
            'key' => DocumentType::LEASE->value,
            'label' => 'Contrato de locação',
            'required_parties' => ['tenant', 'landlord'],
        ])
        ->assertJsonFragment([
            'key' => 'custom:utility_statement',
            'label' => 'Conta de consumo',
        ]);

    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/document-kinds/{$kindId}", [
            'label_en' => 'Utility bill',
        ])
        ->assertOk()
        ->assertJsonPath('data.label_en', 'Utility bill');

    $externalKind = DocumentKind::withoutGlobalScopes()->create([
        'organization_id' => $otherOrganization->id,
        'key' => 'external_kind',
        'label_en' => 'External kind',
        'label_pt_br' => 'Tipo externo',
        'category' => 'other',
        'allowed_scopes' => ['organization'],
    ]);

    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/document-kinds/{$externalKind->id}", [
            'label_en' => 'Leaked kind',
        ])
        ->assertNotFound();
});

it('enforces custom kind scope and expiry capabilities', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property] = createDocumentTestProperty($organization);
    $headers = useDocumentOrganization($user, $organization);
    $kind = DocumentKind::query()->create([
        'organization_id' => $organization->id,
        'key' => 'property_note',
        'label_en' => 'Property note',
        'label_pt_br' => 'Nota do imóvel',
        'category' => 'other',
        'allowed_scopes' => ['property'],
        'supports_expiry' => false,
    ]);

    $basePayload = [
        'type' => DocumentType::CUSTOM->value,
        'document_kind_id' => $kind->id,
        'title' => 'Property note',
        'purpose' => 'Internal property record',
        'file' => fakePdf('property-note.pdf'),
    ];

    $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', $basePayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('context');

    $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            ...$basePayload,
            'property_id' => $property->id,
            'expires_on' => '2027-01-01',
            'file' => fakePdf('property-note-with-expiry.pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expires_on');

    $created = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            ...$basePayload,
            'property_id' => $property->id,
            'file' => fakePdf('valid-property-note.pdf'),
        ]);

    $created
        ->assertCreated()
        ->assertJsonPath('data.custom_kind_id', $kind->id)
        ->assertJsonPath('data.property.id', $property->id);

    $this
        ->withHeaders($headers)
        ->patchJson('/api/v1/documents/' . $created->json('data.id'), [
            'title' => 'Updated property note',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated property note');
});

it('creates immutable revisions and completes signatures only after every signer is complete', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property, $unit] = createDocumentTestProperty($organization);
    $tenant = createDocumentTestTenant($organization);
    $headers = useDocumentOrganization($user, $organization);

    $created = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::INSURANCE_POLICY->value,
            'title' => 'Rental guarantee insurance',
            'purpose' => 'Lease guarantee evidence',
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'requires_signature' => true,
            'expires_on' => '2027-08-31',
            'details' => [
                'provider' => 'Example Seguros',
                'policy_number' => 'POL-2026-001',
                'premium_amount' => '325.50',
                'currency' => 'BRL',
            ],
            'parties' => [[
                'contact_id' => $tenant->id,
                'role' => 'tenant',
                'is_primary' => true,
            ]],
            'signers' => [[
                'contact_id' => $tenant->id,
                'role' => 'tenant',
            ]],
            'file' => fakePdf('policy-v1.pdf'),
            'supporting_files' => [
                fakePdf('receipt.pdf'),
                fakePdf('coverage-summary.pdf'),
            ],
            'supporting_labels' => ['Premium receipt', 'Coverage summary'],
            'supporting_party_visible' => [false, true],
        ]);

    $created
        ->assertCreated()
        ->assertJsonPath('data.current_version.version_number', 1)
        ->assertJsonPath('data.signature_status', 'pending')
        ->assertJsonCount(2, 'data.files.supporting');

    $documentId = $created->json('data.id');
    $revision = $this
        ->withHeaders($headers)
        ->post("/api/v1/documents/{$documentId}/versions", [
            'file' => fakePdf('policy-v2.pdf'),
            'signed_file' => fakePdf('policy-v2-signed.pdf'),
            'notes' => 'Corrected insured unit.',
        ]);

    $revision
        ->assertOk()
        ->assertJsonPath('data.current_version.version_number', 2)
        ->assertJsonPath('data.current_version.finalized_at', null)
        ->assertJsonPath('data.is_signed', false)
        ->assertJsonPath('data.signature_status', 'pending')
        ->assertJsonPath('data.signers.0.status', 'pending')
        ->assertJsonPath('data.files.signed.file_name', 'policy-v2-signed.pdf');

    $signerId = $revision->json('data.signers.0.id');
    $completed = $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/documents/{$documentId}/signers/{$signerId}", [
            'status' => 'signed',
        ]);

    $completed
        ->assertOk()
        ->assertJsonPath('data.is_signed', true)
        ->assertJsonPath('data.signature_status', 'signed');

    expect($completed->json('data.current_version.finalized_at'))->not->toBeNull();

    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/documents/{$documentId}/signers/{$signerId}", [
            'status' => 'pending',
        ])
        ->assertConflict();

    $this
        ->withHeaders($headers)
        ->deleteJson("/api/v1/documents/{$documentId}/signature")
        ->assertConflict();

    $this->assertDatabaseHas('document_audit_events', [
        'document_id' => $documentId,
        'event' => 'document.signer_status_changed',
    ]);
});

it('grants tenant access only through an active share and revokes it immediately', function () {
    Storage::fake('documents');
    [$manager, $organization] = createDocumentTestOrganization();

    $tenantUser = User::query()->create([
        'name' => 'Portal Tenant',
        'first_name' => 'Portal',
        'last_name' => 'Tenant',
        'email' => 'portal-tenant-' . $organization->id . '@example.com',
        'phone' => '+551188880001',
        'password' => 'password',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $tenantUser->assignRole(UserRole::TENANT);
    $tenantUser->organizations()->attach($organization, [
        'role' => OrganizationMemberRole::TENANT->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);
    $tenantContact = Contact::factory()->tenant()->for($organization)->create([
        'user_id' => $tenantUser->id,
        'name' => 'Portal Tenant',
        'email' => $tenantUser->email,
    ]);
    $unlinkedContact = Contact::factory()->tenant()->for($organization)->create([
        'user_id' => null,
        'name' => 'Unlinked Tenant',
        'email' => 'unlinked-' . $organization->id . '@example.com',
    ]);
    $managerHeaders = useDocumentOrganization($manager, $organization);

    $created = $this
        ->withHeaders($managerHeaders)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Tenant handover pack',
            'purpose' => 'Shared move-in documents',
            'parties' => [
                ['contact_id' => $tenantContact->id, 'role' => 'tenant', 'is_primary' => true],
                ['contact_id' => $unlinkedContact->id, 'role' => 'co_tenant'],
            ],
            'file' => fakePdf('handover.pdf'),
            'supporting_files' => [
                fakePdf('internal-checklist.pdf'),
                fakePdf('tenant-instructions.pdf'),
            ],
            'supporting_party_visible' => [false, true],
        ]);

    $created->assertCreated();
    $documentId = $created->json('data.id');

    $tenantHeaders = useDocumentOrganization($tenantUser, $organization);
    $this
        ->withHeaders($tenantHeaders)
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this
        ->withHeaders($tenantHeaders)
        ->getJson("/api/v1/documents/{$documentId}")
        ->assertForbidden();

    $this
        ->withHeaders(useDocumentOrganization($manager, $organization))
        ->postJson("/api/v1/documents/{$documentId}/shares", [
            'user_id' => $tenantUser->id,
            'contact_id' => $unlinkedContact->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('contact_id');

    $shared = $this
        ->withHeaders($managerHeaders)
        ->postJson("/api/v1/documents/{$documentId}/shares", [
            'user_id' => $tenantUser->id,
            'contact_id' => $tenantContact->id,
        ]);

    $shared
        ->assertOk()
        ->assertJsonCount(1, 'data.shares');

    $shareId = $shared->json('data.shares.0.id');
    $hiddenSupportingUrl = $shared->json('data.files.supporting.0.download_url');
    $visibleSupportingUrl = $shared->json('data.files.supporting.1.download_url');

    $this
        ->withHeaders(useDocumentOrganization($tenantUser, $organization))
        ->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $documentId)
        ->assertJsonPath('data.0.details', [])
        ->assertJsonPath('data.0.uploader', null);
    $this
        ->withHeaders($tenantHeaders)
        ->get("/api/v1/documents/{$documentId}/files/original")
        ->assertOk();
    $this
        ->withHeaders($tenantHeaders)
        ->get($hiddenSupportingUrl)
        ->assertForbidden();
    $this
        ->withHeaders($tenantHeaders)
        ->get($visibleSupportingUrl)
        ->assertOk();

    $this
        ->withHeaders(useDocumentOrganization($manager, $organization))
        ->deleteJson("/api/v1/documents/{$documentId}/shares/{$shareId}")
        ->assertOk()
        ->assertJsonCount(0, 'data.shares');

    $this
        ->withHeaders(useDocumentOrganization($tenantUser, $organization))
        ->getJson("/api/v1/documents/{$documentId}")
        ->assertForbidden();
    $this
        ->withHeaders($tenantHeaders)
        ->get("/api/v1/documents/{$documentId}/files/original")
        ->assertForbidden();
});

it('rejects more than twenty supporting files and files larger than ten megabytes', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    $headers = useDocumentOrganization($user, $organization);
    $supportingFiles = [];
    for ($index = 1; $index <= 21; $index++) {
        $supportingFiles[] = fakePdf("supporting-{$index}.pdf");
    }

    $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Too many attachments',
            'purpose' => 'Validate attachment count',
            'file' => fakePdf(),
            'supporting_files' => $supportingFiles,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supporting_files');

    $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Oversized file',
            'purpose' => 'Validate file size',
            'file' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

it('returns 422 when activating a draft without a primary file', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    $document = Document::factory()->for($organization)->create([
        'uploaded_by' => $user->id,
        'lifecycle' => DocumentLifecycle::DRAFT,
    ]);

    $this
        ->withHeaders(useDocumentOrganization($user, $organization))
        ->postJson("/api/v1/documents/{$document->id}/activate")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($document->refresh()->lifecycle)->toBe(DocumentLifecycle::DRAFT);
    $this->assertDatabaseMissing('document_versions', [
        'document_id' => $document->id,
    ]);
    $this->assertDatabaseMissing('document_audit_events', [
        'document_id' => $document->id,
        'event' => 'document.activated',
    ]);
});

it('returns 422 when activating a document without its required parties', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property] = createDocumentTestProperty($organization);
    $headers = useDocumentOrganization($user, $organization);

    $created = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::PROPERTY_MANAGEMENT_AGREEMENT->value,
            'title' => 'Property management agreement',
            'purpose' => 'Authorize property management',
            'property_id' => $property->id,
            'file' => fakePdf('management-agreement.pdf'),
        ]);

    $created
        ->assertCreated()
        ->assertJsonPath('data.lifecycle', DocumentLifecycle::DRAFT->value)
        ->assertJsonPath('data.requires_signature', true);

    $documentId = $created->json('data.id');
    $this
        ->withHeaders($headers)
        ->postJson("/api/v1/documents/{$documentId}/activate")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parties');

    $this->assertDatabaseHas('documents', [
        'id' => $documentId,
        'lifecycle' => DocumentLifecycle::DRAFT->value,
    ]);
    $this->assertDatabaseMissing('document_audit_events', [
        'document_id' => $documentId,
        'event' => 'document.activated',
    ]);
});

it('returns 422 when activating a signature-required document without a signer', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    [$property] = createDocumentTestProperty($organization);
    $owner = Contact::factory()->for($organization)->create(['name' => 'Property Owner']);
    $manager = Contact::factory()->for($organization)->create(['name' => 'Property Manager']);
    $headers = useDocumentOrganization($user, $organization);

    $created = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::PROPERTY_MANAGEMENT_AGREEMENT->value,
            'title' => 'Signed management agreement',
            'purpose' => 'Authorize property management',
            'property_id' => $property->id,
            'parties' => [
                ['contact_id' => $owner->id, 'role' => 'owner', 'is_primary' => true],
                ['contact_id' => $manager->id, 'role' => 'manager'],
            ],
            'file' => fakePdf('signed-management-agreement.pdf'),
        ]);

    $created->assertCreated();

    $documentId = $created->json('data.id');
    $this
        ->withHeaders($headers)
        ->postJson("/api/v1/documents/{$documentId}/activate")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('signers');

    $this->assertDatabaseHas('documents', [
        'id' => $documentId,
        'lifecycle' => DocumentLifecycle::DRAFT->value,
    ]);
    $this->assertDatabaseMissing('document_audit_events', [
        'document_id' => $documentId,
        'event' => 'document.activated',
    ]);
});

it('activates a ready draft, supersedes its predecessor, and returns 409 for later draft-only mutations', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    $headers = useDocumentOrganization($user, $organization);

    $predecessor = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'lifecycle' => DocumentLifecycle::ACTIVE->value,
            'title' => 'Operations policy v1',
            'purpose' => 'Property operations',
            'file' => fakePdf('operations-policy-v1.pdf'),
        ]);
    $predecessor->assertCreated();

    $successor = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'title' => 'Operations policy v2',
            'purpose' => 'Property operations',
            'supersedes_document_id' => $predecessor->json('data.id'),
            'file' => fakePdf('operations-policy-v2.pdf'),
        ]);
    $successor->assertCreated();

    $documentId = $successor->json('data.id');
    $activated = $this
        ->withHeaders($headers)
        ->postJson("/api/v1/documents/{$documentId}/activate");

    $activated
        ->assertOk()
        ->assertJsonPath('data.lifecycle', DocumentLifecycle::ACTIVE->value)
        ->assertJsonPath('data.capabilities.can_activate', false)
        ->assertJsonPath('data.current_version.version_number', 1);
    $this->assertDatabaseHas('documents', [
        'id' => $predecessor->json('data.id'),
        'lifecycle' => DocumentLifecycle::SUPERSEDED->value,
    ]);
    $this->assertDatabaseHas('documents', [
        'id' => $documentId,
        'lifecycle' => DocumentLifecycle::ACTIVE->value,
    ]);
    $this->assertDatabaseHas('document_audit_events', [
        'document_id' => $documentId,
        'actor_id' => $user->id,
        'event' => 'document.activated',
    ]);

    $this
        ->withHeaders($headers)
        ->postJson("/api/v1/documents/{$documentId}/activate")
        ->assertConflict();
    $this
        ->withHeaders($headers)
        ->patchJson("/api/v1/documents/{$documentId}", ['parties' => []])
        ->assertConflict();

    $this->assertDatabaseCount('document_versions', 2);
    $this->assertDatabaseHas('documents', [
        'id' => $documentId,
        'lifecycle' => DocumentLifecycle::ACTIVE->value,
    ]);
});

it('archives operational documents and rejects later file mutations', function () {
    Storage::fake('documents');
    [$user, $organization] = createDocumentTestOrganization();
    $headers = useDocumentOrganization($user, $organization);

    $created = $this
        ->withHeaders($headers)
        ->post('/api/v1/documents', [
            'type' => DocumentType::GENERIC->value,
            'lifecycle' => DocumentLifecycle::ACTIVE->value,
            'title' => 'Active operations manual',
            'purpose' => 'Property operations',
            'file' => fakePdf('operations.pdf'),
        ]);

    $created->assertCreated();
    $documentId = $created->json('data.id');

    $this
        ->withHeaders($headers)
        ->deleteJson("/api/v1/documents/{$documentId}")
        ->assertConflict();

    $this
        ->withHeaders($headers)
        ->postJson("/api/v1/documents/{$documentId}/archive")
        ->assertOk()
        ->assertJsonPath('data.lifecycle', DocumentLifecycle::ARCHIVED->value);

    $this
        ->withHeaders($headers)
        ->post("/api/v1/documents/{$documentId}/versions", [
            'file' => fakePdf('operations-v2.pdf'),
        ])
        ->assertConflict();
});
