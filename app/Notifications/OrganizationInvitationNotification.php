<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $token,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/')
            . '/accept-invitation?token=' . urlencode($this->token);

        return (new MailMessage)
            ->subject(__('messages.invitation.email.subject', [
                'organization' => $this->invitation->organization->title,
            ]))
            ->greeting(__('messages.invitation.email.greeting'))
            ->line(__('messages.invitation.email.introduction', [
                'inviter' => $this->invitation->inviter->name,
                'organization' => $this->invitation->organization->title,
                'role' => __('messages.organization_roles.' . $this->invitation->role->value),
            ]))
            ->action(__('messages.invitation.email.action'), $url)
            ->line(__('messages.invitation.email.expiry'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->invitation->organization_id,
            'invitation_id' => $this->invitation->id,
            'role' => $this->invitation->role->value,
        ];
    }
}
