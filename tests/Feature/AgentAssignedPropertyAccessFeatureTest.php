<?php

use App\Enums\ContactAssignmentRole;
use App\Enums\ContactAssignmentSource;
use App\Enums\DocumentType;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * @return array{
 *     agent: User,
 *     organization: Organization,
 *     assignedProperty: Property,
 *     unassignedProperty: Property,
 *     assignedUnit: Unit,
 *     unassignedUnit: Unit,
 *     assignedContact: Contact,
 *     unassignedContact: Contact,
 *     assignedLease: Lease,
 *     unassignedLease: Lease,
 *     assignedDocument: Document,
 *     unassignedDocument: Document
 * }
 */
function createPropertyScopedAgentFixture(): array
{
    $owner = User::factory()->create();
    $agent = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create();
    $organization->users()->attach($agent, [
        'role' => OrganizationMemberRole::AGENT->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);

    $assignedProperty = Property::factory()->for($organization)->create([
        'title' => 'Assigned property',
        'property_type' => PropertyType::MULTI_UNIT,
    ]);
    $unassignedProperty = Property::factory()->for($organization)->create([
        'title' => 'Unassigned property',
        'property_type' => PropertyType::MULTI_UNIT,
    ]);
    $assignedUnit = Unit::factory()->for($assignedProperty)->create([
        'name' => 'Assigned unit',
        'unit_type' => UnitType::APARTMENT,
    ]);
    $unassignedUnit = Unit::factory()->for($unassignedProperty)->create([
        'name' => 'Unassigned unit',
        'unit_type' => UnitType::APARTMENT,
    ]);

    $agentContact = Contact::factory()->agent()->for($organization)->create([
        'user_id' => $agent->id,
    ]);
    createAgentAssignment($organization, $agentContact, $assignedProperty);

    $assignedContact = Contact::factory()->tenant()->for($organization)->create([
        'name' => 'Assigned contact',
    ]);
    createAgentAssignment(
        $organization,
        $assignedContact,
        $assignedProperty,
        $assignedUnit,
        ContactAssignmentRole::TENANT,
    );
    $unassignedContact = Contact::factory()->tenant()->for($organization)->create([
        'name' => 'Unassigned contact',
    ]);
    createAgentAssignment(
        $organization,
        $unassignedContact,
        $unassignedProperty,
        $unassignedUnit,
        ContactAssignmentRole::TENANT,
    );

    $assignedLease = createAgentScopeLease(
        $organization,
        $assignedProperty,
        $assignedUnit,
        'Assigned lease',
    );
    $unassignedLease = createAgentScopeLease(
        $organization,
        $unassignedProperty,
        $unassignedUnit,
        'Unassigned lease',
    );
    $assignedDocument = Document::factory()->for($organization)->create([
        'property_id' => $assignedProperty->id,
        'unit_id' => $assignedUnit->id,
        'uploaded_by' => $owner->id,
        'type' => DocumentType::GENERIC,
        'title' => 'Assigned document',
    ]);
    $unassignedDocument = Document::factory()->for($organization)->create([
        'property_id' => $unassignedProperty->id,
        'unit_id' => $unassignedUnit->id,
        'uploaded_by' => $owner->id,
        'type' => DocumentType::GENERIC,
        'title' => 'Unassigned document',
    ]);

    return compact(
        'agent',
        'organization',
        'assignedProperty',
        'unassignedProperty',
        'assignedUnit',
        'unassignedUnit',
        'assignedContact',
        'unassignedContact',
        'assignedLease',
        'unassignedLease',
        'assignedDocument',
        'unassignedDocument',
    );
}

/**
 * @return array{
 *     agent: User,
 *     organization: Organization,
 *     property: Property,
 *     agentContact: Contact,
 *     assignedUnit: Unit,
 *     siblingUnit: Unit,
 *     assignedContact: Contact,
 *     siblingContact: Contact,
 *     assignedLease: Lease,
 *     siblingLease: Lease,
 *     assignedDocument: Document,
 *     siblingDocument: Document
 * }
 */
function createUnitScopedAgentFixture(): array
{
    $owner = User::factory()->create();
    $agent = User::factory()->create();
    $organization = Organization::factory()->for($owner, 'owner')->create();
    $organization->users()->attach($agent, [
        'role' => OrganizationMemberRole::AGENT->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);
    $property = Property::factory()->for($organization)->create([
        'title' => 'Shared building',
        'property_type' => PropertyType::MULTI_UNIT,
    ]);
    $assignedUnit = Unit::factory()->for($property)->create([
        'name' => 'Assigned unit',
        'unit_type' => UnitType::APARTMENT,
    ]);
    $siblingUnit = Unit::factory()->for($property)->create([
        'name' => 'Sibling unit',
        'unit_type' => UnitType::APARTMENT,
    ]);

    $agentContact = Contact::factory()->agent()->for($organization)->create([
        'user_id' => $agent->id,
    ]);
    createAgentAssignment($organization, $agentContact, $property, $assignedUnit);

    $assignedContact = Contact::factory()->tenant()->for($organization)->create([
        'name' => 'Assigned unit contact',
    ]);
    createAgentAssignment(
        $organization,
        $assignedContact,
        $property,
        $assignedUnit,
        ContactAssignmentRole::TENANT,
    );
    $siblingContact = Contact::factory()->tenant()->for($organization)->create([
        'name' => 'Sibling unit contact',
    ]);
    createAgentAssignment(
        $organization,
        $siblingContact,
        $property,
        $siblingUnit,
        ContactAssignmentRole::TENANT,
    );

    $assignedLease = createAgentScopeLease($organization, $property, $assignedUnit, 'Assigned unit lease');
    $siblingLease = createAgentScopeLease($organization, $property, $siblingUnit, 'Sibling unit lease');
    $assignedDocument = Document::factory()->for($organization)->create([
        'property_id' => $property->id,
        'unit_id' => $assignedUnit->id,
        'uploaded_by' => $owner->id,
        'type' => DocumentType::GENERIC,
        'title' => 'Assigned unit document',
    ]);
    $siblingDocument = Document::factory()->for($organization)->create([
        'property_id' => $property->id,
        'unit_id' => $siblingUnit->id,
        'uploaded_by' => $owner->id,
        'type' => DocumentType::GENERIC,
        'title' => 'Sibling unit document',
    ]);

    return compact(
        'agent',
        'organization',
        'property',
        'agentContact',
        'assignedUnit',
        'siblingUnit',
        'assignedContact',
        'siblingContact',
        'assignedLease',
        'siblingLease',
        'assignedDocument',
        'siblingDocument',
    );
}

function createAgentAssignment(
    Organization $organization,
    Contact $contact,
    Property $property,
    ?Unit $unit = null,
    ContactAssignmentRole $role = ContactAssignmentRole::AGENT,
): ContactAssignment {
    return ContactAssignment::query()->create([
        'organization_id' => $organization->id,
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'unit_id' => $unit?->id,
        'role' => $role,
        'source' => ContactAssignmentSource::MANUAL,
        'is_primary' => true,
    ]);
}

function createAgentScopeLease(
    Organization $organization,
    Property $property,
    Unit $unit,
    string $title,
): Lease {
    return Lease::query()->create([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'unit_id' => $unit->id,
        'workflow_status' => LeaseWorkflowStatus::DRAFT,
        'title' => $title,
        'property_title_snapshot' => $property->title,
        'unit_name_snapshot' => $unit->name,
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'currency' => 'BRL',
    ]);
}

/** @param array<string, mixed> $fixture */
function useAgentScopeOrganization(array $fixture): array
{
    Sanctum::actingAs($fixture['agent']);

    return [
        'Accept' => 'application/json',
        'X-Organization-Id' => (string) $fixture['organization']->id,
    ];
}

it('lists only resources from properties assigned to the agent', function (
    string $endpoint,
    string $assignedKey,
    string $unassignedKey,
) {
    $fixture = createPropertyScopedAgentFixture();
    $endpoint = str_replace('{property}', (string) $fixture['assignedProperty']->id, $endpoint);

    $response = $this
        ->withHeaders(useAgentScopeOrganization($fixture))
        ->getJson($endpoint)
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)
        ->toContain($fixture[$assignedKey]->id)
        ->not->toContain($fixture[$unassignedKey]->id);
})->with([
    'properties' => ['/api/v1/properties', 'assignedProperty', 'unassignedProperty'],
    'units' => ['/api/v1/properties/{property}/units', 'assignedUnit', 'unassignedUnit'],
    'contacts' => ['/api/v1/contacts', 'assignedContact', 'unassignedContact'],
    'leases' => ['/api/v1/leases', 'assignedLease', 'unassignedLease'],
    'documents' => ['/api/v1/documents', 'assignedDocument', 'unassignedDocument'],
]);

it('allows direct access to assigned resources and returns 403 for unassigned resources', function (
    string $assignedRoute,
    string $unassignedRoute,
) {
    $fixture = createPropertyScopedAgentFixture();
    $replace = fn (string $route): string => strtr($route, [
        '{assigned_property}' => (string) $fixture['assignedProperty']->id,
        '{unassigned_property}' => (string) $fixture['unassignedProperty']->id,
        '{assigned_unit}' => (string) $fixture['assignedUnit']->id,
        '{unassigned_unit}' => (string) $fixture['unassignedUnit']->id,
        '{assigned_contact}' => (string) $fixture['assignedContact']->id,
        '{unassigned_contact}' => (string) $fixture['unassignedContact']->id,
        '{assigned_lease}' => (string) $fixture['assignedLease']->id,
        '{unassigned_lease}' => (string) $fixture['unassignedLease']->id,
        '{assigned_document}' => (string) $fixture['assignedDocument']->id,
        '{unassigned_document}' => (string) $fixture['unassignedDocument']->id,
    ]);
    $headers = useAgentScopeOrganization($fixture);

    $this->withHeaders($headers)->getJson($replace($assignedRoute))->assertOk();
    $this->withHeaders($headers)->getJson($replace($unassignedRoute))->assertForbidden();
})->with([
    'properties' => [
        '/api/v1/properties/{assigned_property}',
        '/api/v1/properties/{unassigned_property}',
    ],
    'units' => [
        '/api/v1/properties/{assigned_property}/units/{assigned_unit}',
        '/api/v1/properties/{unassigned_property}/units/{unassigned_unit}',
    ],
    'contacts' => [
        '/api/v1/contacts/{assigned_contact}',
        '/api/v1/contacts/{unassigned_contact}',
    ],
    'leases' => [
        '/api/v1/leases/{assigned_lease}',
        '/api/v1/leases/{unassigned_lease}',
    ],
    'documents' => [
        '/api/v1/documents/{assigned_document}',
        '/api/v1/documents/{unassigned_document}',
    ],
]);

it('hides sibling-unit resources from an agent assigned to one unit', function (
    string $endpoint,
    string $assignedKey,
    string $siblingKey,
) {
    $fixture = createUnitScopedAgentFixture();
    $endpoint = str_replace('{property}', (string) $fixture['property']->id, $endpoint);

    $response = $this
        ->withHeaders(useAgentScopeOrganization($fixture))
        ->getJson($endpoint)
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)
        ->toContain($fixture[$assignedKey]->id)
        ->not->toContain($fixture[$siblingKey]->id);
})->with([
    'units' => ['/api/v1/properties/{property}/units', 'assignedUnit', 'siblingUnit'],
    'contacts' => ['/api/v1/contacts', 'assignedContact', 'siblingContact'],
    'leases' => ['/api/v1/leases', 'assignedLease', 'siblingLease'],
    'documents' => ['/api/v1/documents', 'assignedDocument', 'siblingDocument'],
]);

it('returns 403 for a sibling unit when the agent has a unit-scoped assignment', function () {
    $fixture = createUnitScopedAgentFixture();
    $headers = useAgentScopeOrganization($fixture);

    $this
        ->withHeaders($headers)
        ->getJson("/api/v1/properties/{$fixture['property']->id}/units/{$fixture['assignedUnit']->id}")
        ->assertOk();
    $this
        ->withHeaders($headers)
        ->getJson("/api/v1/properties/{$fixture['property']->id}/units/{$fixture['siblingUnit']->id}")
        ->assertForbidden();
});

it('returns 403 when a unit-scoped agent tries to grant their contact access to a sibling unit', function () {
    $fixture = createUnitScopedAgentFixture();

    $this
        ->withHeaders(useAgentScopeOrganization($fixture))
        ->postJson("/api/v1/contacts/{$fixture['agentContact']->id}/assignments", [
            'property_id' => $fixture['property']->id,
            'unit_id' => $fixture['siblingUnit']->id,
            'role' => ContactAssignmentRole::AGENT->value,
            'is_primary' => true,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('contact_assignments', [
        'contact_id' => $fixture['agentContact']->id,
        'unit_id' => $fixture['siblingUnit']->id,
        'role' => ContactAssignmentRole::AGENT->value,
    ]);
});

it('returns 403 when a unit-scoped agent creates a property-wide contact', function () {
    $fixture = createUnitScopedAgentFixture();

    $this
        ->withHeaders(useAgentScopeOrganization($fixture))
        ->postJson('/api/v1/contacts', [
            'name' => 'Unauthorized property contact',
            'type' => 'tenant',
            'property_ids' => [$fixture['property']->id],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('contacts', [
        'organization_id' => $fixture['organization']->id,
        'name' => 'Unauthorized property contact',
    ]);
});
