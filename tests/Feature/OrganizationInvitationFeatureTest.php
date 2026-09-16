<?php

use App\Enums\ContactPersonType;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function invitationOrganizationOwner(): array
{
    $owner = User::factory()->create();
    $owner->assignRole(UserRole::LANDLORD);
    $organization = Organization::factory()->create(['owner_id' => $owner->id]);
    $owner->organizations()->attach($organization, [
        'role' => OrganizationMemberRole::OWNER->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);

    return [$owner, $organization];
}

function invitationHeaders(User $user, Organization $organization, string $locale = 'en'): array
{
    Sanctum::actingAs($user);

    return [
        'Accept' => 'application/json',
        'Accept-Language' => $locale,
        'X-Organization-Id' => (string) $organization->id,
    ];
}

it('creates a single-use tenant invitation from an organization contact', function () {
    Notification::fake();
    [$owner, $organization] = invitationOrganizationOwner();
    $contact = Contact::factory()->for($organization)->create([
        'name' => 'Ana Inquilina',
        'email' => 'ana@example.com',
        'type' => ContactPersonType::TENANT,
        'preferred_locale' => 'pt-BR',
    ]);

    $response = $this
        ->withHeaders(invitationHeaders($owner, $organization, 'pt-BR'))
        ->postJson('/api/v1/organization-invitations', [
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'role' => OrganizationMemberRole::TENANT->value,
            'locale' => 'pt-BR',
        ]);

    $response
        ->assertCreated()
        ->assertHeader('Content-Language', 'pt-BR')
        ->assertJsonPath('data.role', 'tenant')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('data.token_hash');

    $invitation = OrganizationInvitation::query()->findOrFail($response->json('data.id'));
    expect($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->expires_at->diffInDays($invitation->created_at))->toBe(7.0);

    Notification::assertCount(1);
});

it('accepts an invitation for a new user and links the tenant contact exactly once', function () {
    Notification::fake();
    [$owner, $organization] = invitationOrganizationOwner();
    $contact = Contact::factory()->for($organization)->create([
        'name' => 'Joao Tenant',
        'email' => 'joao@example.com',
        'type' => ContactPersonType::TENANT,
    ]);

    $this
        ->withHeaders(invitationHeaders($owner, $organization))
        ->postJson('/api/v1/organization-invitations', [
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'role' => OrganizationMemberRole::TENANT->value,
            'locale' => 'pt-BR',
        ])
        ->assertCreated();

    $token = null;
    Notification::assertSentOnDemand(
        OrganizationInvitationNotification::class,
        function (OrganizationInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        }
    );

    $acceptance = $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'Joao Tenant',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ]);

    $acceptance
        ->assertOk()
        ->assertJsonPath('data.email', 'joao@example.com')
        ->assertJsonPath('data.locale', 'pt-BR');

    $user = User::query()->where('email', 'joao@example.com')->firstOrFail();
    $membership = $user->organizations()->whereKey($organization->id)->firstOrFail()->pivot;

    expect($membership->role)->toBe(OrganizationMemberRole::TENANT->value)
        ->and($membership->status)->toBe(OrganizationMemberStatus::ACTIVE->value)
        ->and($contact->refresh()->user_id)->toBe($user->id);

    $this->postJson("/api/v1/invitations/{$token}/accept", [
        'name' => 'Joao Tenant',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertGone();
});

it('prevents tenant members from inviting organization users', function () {
    [$owner, $organization] = invitationOrganizationOwner();
    $tenant = User::factory()->create();
    $tenant->assignRole(UserRole::TENANT);
    $tenant->organizations()->attach($organization, [
        'role' => OrganizationMemberRole::TENANT->value,
        'status' => OrganizationMemberStatus::ACTIVE->value,
        'accepted_at' => now(),
    ]);

    $this
        ->withHeaders(invitationHeaders($tenant, $organization))
        ->postJson('/api/v1/organization-invitations', [
            'email' => 'blocked@example.com',
            'role' => OrganizationMemberRole::AGENT->value,
        ])
        ->assertForbidden();
});

it('returns localized validation for invitation requests', function () {
    [$owner, $organization] = invitationOrganizationOwner();

    $this
        ->withHeaders(invitationHeaders($owner, $organization, 'pt-BR'))
        ->postJson('/api/v1/organization-invitations', [])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'pt-BR')
        ->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.');
});
