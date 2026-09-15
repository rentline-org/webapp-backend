<?php

use App\Enums\ContactPersonType;
use App\Enums\PropertyType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

function createContactTestOrganization(UserRole $role = UserRole::LANDLORD): array
{
    $sequence = User::query()->count() + 1;
    $user = User::query()->create([
        'name' => "Contact Test User {$sequence}",
        'first_name' => 'Contact',
        'last_name' => 'Tester',
        'email' => "contact-test-{$sequence}@example.com",
        'phone' => "+55110000{$sequence}",
        'password' => 'password',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole($role);

    $organization = Organization::factory()->create([
        'owner_id' => $user->id,
    ]);
    $user->organizations()->attach($organization);

    return [$user, $organization];
}

function createContactTestProperty(Organization $organization, string $title): Property
{
    return Property::query()->create([
        'organization_id' => $organization->id,
        'slug' => str($title)->slug()->toString(),
        'title' => $title,
        'description' => null,
        'address' => '123 Main Street',
        'city' => 'Sao Paulo',
        'state' => 'SP',
        'postal_code' => '01000-000',
        'country' => 'BR',
        'property_type' => PropertyType::SINGLE_UNIT,
    ]);
}

function useContactOrganization(User $user, Organization $organization): array
{
    Sanctum::actingAs($user);

    return [
        'Accept' => 'application/json',
        'X-Organization-Id' => (string) $organization->id,
    ];
}

it('returns 401 when no token is provided', function () {
    $this->getJson('/api/v1/contacts')->assertUnauthorized();
});

it('returns 403 when no active organization is selected', function () {
    [$user] = createContactTestOrganization();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/contacts')->assertForbidden();
});

it('does not resolve a contact route without an active organization', function () {
    [$user, $organization] = createContactTestOrganization();
    $contact = Contact::factory()->owner()->for($organization)->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
});

it('creates an organization contact and assigns properties', function () {
    [$user, $organization] = createContactTestOrganization();
    [, $otherOrganization] = createContactTestOrganization();
    $firstProperty = createContactTestProperty($organization, 'First Property');
    $secondProperty = createContactTestProperty($organization, 'Second Property');

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->postJson('/api/v1/contacts', [
            'organization_id' => $otherOrganization->id,
            'name' => 'Alex Agent',
            'email' => 'alex@example.com',
            'phone' => '+55 11 99999-9999',
            'type' => ContactPersonType::AGENT->value,
            'property_ids' => [$firstProperty->id, $secondProperty->id],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.name', 'Alex Agent')
        ->assertJsonPath('data.type', ContactPersonType::AGENT->value)
        ->assertJsonCount(2, 'data.property_ids')
        ->assertJsonCount(2, 'data.properties');

    $contactId = $response->json('data.id');

    $this->assertDatabaseHas('contacts', [
        'id' => $contactId,
        'organization_id' => $organization->id,
        'email' => 'alex@example.com',
    ]);
    $this->assertDatabaseHas('contact_property', [
        'contact_id' => $contactId,
        'property_id' => $firstProperty->id,
    ]);
    $this->assertDatabaseHas('contact_property', [
        'contact_id' => $contactId,
        'property_id' => $secondProperty->id,
    ]);
});

it('lists only matching contacts in the active organization', function () {
    [$user, $organization] = createContactTestOrganization();
    [, $otherOrganization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Managed Property');
    $otherProperty = createContactTestProperty($organization, 'Other Property');
    $externalProperty = createContactTestProperty($otherOrganization, 'External Property');

    $matchingContact = Contact::factory()->owner()->for($organization)->create([
        'name' => 'Maria Owner',
    ]);
    $matchingContact->properties()->attach($property);

    $differentProperty = Contact::factory()->owner()->for($organization)->create([
        'name' => 'Maria Elsewhere',
    ]);
    $differentProperty->properties()->attach($otherProperty);

    $differentType = Contact::factory()->agent()->for($organization)->create([
        'name' => 'Maria Agent',
    ]);
    $differentType->properties()->attach($property);

    $externalContact = Contact::factory()->owner()->for($otherOrganization)->create([
        'name' => 'Maria External',
    ]);
    $externalContact->properties()->attach($externalProperty);

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->getJson('/api/v1/contacts?search=maria&type=owner&property_id=' . $property->id);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingContact->id)
        ->assertJsonMissing(['id' => $externalContact->id]);
});

it('rejects malformed contact filters before querying the database', function () {
    [$user, $organization] = createContactTestOrganization();

    $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->getJson('/api/v1/contacts?search[]=x&type[]=owner&property_id=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search', 'type', 'property_id']);
});

it('shows a contact with slim property data', function () {
    [$user, $organization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Slim Property');
    $contact = Contact::factory()->tenant()->for($organization)->create();
    $contact->properties()->attach($property);

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->getJson("/api/v1/contacts/{$contact->id}");

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.property_ids.0', $property->id)
        ->assertJsonPath('data.properties.0', [
            'id' => $property->id,
            'slug' => $property->slug,
            'title' => $property->title,
        ]);
});

it('updates fields and preserves property assignments when property_ids is absent', function () {
    [$user, $organization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Preserved Property');
    $contact = Contact::factory()->tenant()->for($organization)->create([
        'email' => 'tenant@example.com',
    ]);
    $contact->properties()->attach($property);

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->patchJson("/api/v1/contacts/{$contact->id}", [
            'name' => 'Updated Tenant',
            'email' => null,
            'type' => ContactPersonType::OWNER->value,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Tenant')
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.type', ContactPersonType::OWNER->value)
        ->assertJsonPath('data.property_ids.0', $property->id);

    $this->assertDatabaseHas('contact_property', [
        'contact_id' => $contact->id,
        'property_id' => $property->id,
    ]);
});

it('detaches all properties when property_ids is an empty array', function () {
    [$user, $organization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Detached Property');
    $contact = Contact::factory()->tenant()->for($organization)->create();
    $contact->properties()->attach($property);

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->patchJson("/api/v1/contacts/{$contact->id}", [
            'property_ids' => [],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.property_ids', [])
        ->assertJsonPath('data.properties', []);

    $this->assertDatabaseMissing('contact_property', [
        'contact_id' => $contact->id,
    ]);
});

it('deletes a contact and its property assignments', function () {
    [$user, $organization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Deleted Contact Property');
    $contact = Contact::factory()->agent()->for($organization)->create();
    $contact->properties()->attach($property);

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->deleteJson("/api/v1/contacts/{$contact->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    $this->assertDatabaseMissing('contact_property', ['contact_id' => $contact->id]);
});

it('returns 422 for invalid contact data and cross-organization properties', function () {
    [$user, $organization] = createContactTestOrganization();
    [, $otherOrganization] = createContactTestOrganization();
    $externalProperty = createContactTestProperty($otherOrganization, 'Unavailable Property');

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->postJson('/api/v1/contacts', [
            'email' => 'not-an-email',
            'type' => 'vendor',
            'property_ids' => [$externalProperty->id, $externalProperty->id],
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'type',
            'property_ids.0',
            'property_ids.1',
        ]);

    $this->assertDatabaseMissing('contacts', ['email' => 'not-an-email']);
});

it('requires an explicit contact type when creating a contact', function () {
    [$user, $organization] = createContactTestOrganization();

    $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->postJson('/api/v1/contacts', [
            'name' => 'Missing Type',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

it('keeps a contact when one of its properties is deleted', function () {
    [, $organization] = createContactTestOrganization();
    $firstProperty = createContactTestProperty($organization, 'Disposable Property');
    $secondProperty = createContactTestProperty($organization, 'Retained Property');
    $contact = Contact::factory()->owner()->for($organization)->create();
    $contact->properties()->attach([$firstProperty->id, $secondProperty->id]);

    $firstProperty->delete();

    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    $this->assertDatabaseMissing('contact_property', [
        'contact_id' => $contact->id,
        'property_id' => $firstProperty->id,
    ]);
    $this->assertDatabaseHas('contact_property', [
        'contact_id' => $contact->id,
        'property_id' => $secondProperty->id,
    ]);
});

it('returns 403 when a tenant user tries to manage contacts', function () {
    [$user, $organization] = createContactTestOrganization(UserRole::TENANT);

    $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->getJson('/api/v1/contacts')
        ->assertForbidden();
});

it('authorizes contact writes before validating tenant input', function () {
    [$user, $organization] = createContactTestOrganization(UserRole::TENANT);

    $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->postJson('/api/v1/contacts', [
            'email' => 'not-an-email',
            'property_ids' => [999999],
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('contacts', 0);
});

it('returns 404 when updating a contact in another organization', function () {
    [$user, $organization] = createContactTestOrganization();
    [, $otherOrganization] = createContactTestOrganization();
    $externalContact = Contact::factory()->owner()->for($otherOrganization)->create();

    $response = $this
        ->withHeaders(useContactOrganization($user, $organization))
        ->patchJson("/api/v1/contacts/{$externalContact->id}", [
            'name' => 'Unauthorized change',
        ]);

    $response->assertNotFound();
    $this->assertDatabaseMissing('contacts', [
        'id' => $externalContact->id,
        'name' => 'Unauthorized change',
    ]);
});

it('backfills legacy property assignments into the contact property pivot', function () {
    [, $organization] = createContactTestOrganization();
    $property = createContactTestProperty($organization, 'Legacy Property');
    $migration = require database_path(
        'migrations/2026_09_15_142226_create_contact_property_table_and_migrate_contacts.php'
    );

    $migration->down();

    $contactId = DB::table('contacts')->insertGetId([
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'name' => 'Legacy Owner',
        'email' => 'legacy-owner@example.com',
        'phone' => null,
        'type' => ContactPersonType::OWNER->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    $this->assertDatabaseHas('contact_property', [
        'contact_id' => $contactId,
        'property_id' => $property->id,
    ]);
    expect(Schema::hasColumn('contacts', 'property_id'))->toBeFalse();
});
