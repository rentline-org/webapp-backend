<?php

namespace App\Services\Organization;

use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrganizationInvitationService
{
    /**
     * @param array{email: string, role: string, locale?: string, contact_id?: int|null} $data
     */
    public function create(User $inviter, int $organizationId, array $data): OrganizationInvitation
    {
        [$invitation, $token] = DB::transaction(function () use ($inviter, $organizationId, $data): array {
            $email = mb_strtolower(trim($data['email']));
            $role = OrganizationMemberRole::from($data['role']);
            $contactId = $data['contact_id'] ?? null;

            if ($role === OrganizationMemberRole::TENANT) {
                $contact = Contact::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->findOrFail($contactId);

                if ($contact->email === null) {
                    throw ValidationException::withMessages([
                        'contact_id' => __('messages.invitation.tenant_contact_email_required'),
                    ]);
                }

                if (mb_strtolower($contact->email) !== $email) {
                    throw ValidationException::withMessages([
                        'email' => __('messages.invitation.contact_email_mismatch'),
                    ]);
                }
            }

            $existingUser = User::query()->where('email', $email)->first();

            if (
                $existingUser?->organizations()
                    ->whereKey($organizationId)
                    ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' => __('messages.invitation.already_member'),
                ]);
            }

            OrganizationInvitation::query()
                ->where('organization_id', $organizationId)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $token = Str::random(64);
            $invitation = OrganizationInvitation::query()->create([
                'organization_id' => $organizationId,
                'contact_id' => $contactId,
                'invited_by' => $inviter->id,
                'email' => $email,
                'role' => $role,
                'locale' => $data['locale'] ?? 'en',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);

            return [$invitation, $token];
        });

        $this->sendNotification($invitation, $token);

        return $invitation->load(['organization', 'contact', 'inviter']);
    }

    public function findUsableByToken(string $token): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::query()
            ->with(['organization', 'contact', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null) {
            throw new HttpException(404, __('messages.invitation.not_found'));
        }

        if (! $invitation->isPending()) {
            throw new HttpException(410, __('messages.invitation.unavailable'));
        }

        return $invitation;
    }

    /** @param array{name?: string|null, password?: string|null} $data */
    public function accept(string $token, array $data, ?User $authenticatedUser = null): User
    {
        return DB::transaction(function () use ($token, $data, $authenticatedUser): User {
            $invitation = OrganizationInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new HttpException(404, __('messages.invitation.not_found'));
            }

            if (! $invitation->isPending()) {
                throw new HttpException(410, __('messages.invitation.unavailable'));
            }

            if (
                $authenticatedUser !== null
                && mb_strtolower($authenticatedUser->email) !== mb_strtolower($invitation->email)
            ) {
                throw new HttpException(403, __('messages.invitation.email_mismatch'));
            }

            $user = $authenticatedUser
                ?? User::query()->where('email', $invitation->email)->first();

            if ($user === null) {
                if (blank($data['name'] ?? null) || blank($data['password'] ?? null)) {
                    throw ValidationException::withMessages([
                        'name' => __('messages.invitation.name_required'),
                        'password' => __('messages.invitation.password_required'),
                    ]);
                }

                $name = trim($data['name']);
                $firstName = Str::before($name, ' ');
                $lastName = Str::contains($name, ' ') ? Str::after($name, ' ') : null;

                $user = User::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => $name,
                    'email' => $invitation->email,
                    'locale' => $invitation->locale,
                    'password' => $data['password'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            }

            $membership = [
                'role' => $invitation->role->value,
                'status' => OrganizationMemberStatus::ACTIVE->value,
                'invited_by' => $invitation->invited_by,
                'accepted_at' => now(),
            ];

            $user->organizations()->syncWithoutDetaching([
                $invitation->organization_id => $membership,
            ]);
            $user->organizations()->updateExistingPivot($invitation->organization_id, $membership);

            $globalRole = $invitation->role === OrganizationMemberRole::TENANT
                ? UserRole::TENANT
                : UserRole::LANDLORD;

            if (Role::query()->where('name', $globalRole->value)->exists() && ! $user->hasRole($globalRole)) {
                $user->assignRole($globalRole);
            }

            if ($invitation->contact_id !== null) {
                $contact = Contact::withoutGlobalScopes()
                    ->where('organization_id', $invitation->organization_id)
                    ->find($invitation->contact_id);

                if ($contact !== null) {
                    $contact->user_id = $user->id;
                    $contact->save();
                }
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user->load(['roles', 'organizations']);
        });
    }

    public function resend(OrganizationInvitation $invitation): OrganizationInvitation
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw new HttpException(409, __('messages.invitation.cannot_resend'));
        }

        $token = Str::random(64);
        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ])->save();

        $this->sendNotification($invitation, $token);

        return $invitation->refresh()->load(['organization', 'contact', 'inviter']);
    }

    public function revoke(OrganizationInvitation $invitation): void
    {
        if ($invitation->accepted_at !== null) {
            throw new HttpException(409, __('messages.invitation.cannot_revoke'));
        }

        if ($invitation->revoked_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
        }
    }

    private function sendNotification(OrganizationInvitation $invitation, string $token): void
    {
        Notification::route('mail', $invitation->email)
            ->notify(
                (new OrganizationInvitationNotification($invitation, $token))
                    ->locale($invitation->locale)
                    ->afterCommit()
            );
    }
}
