<?php

namespace App\Notifications;

use App\Models\ActionItem;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OperationsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    /** @param Collection<int, ActionItem> $actionItems */
    public function __construct(
        public Organization $organization,
        public Collection $actionItems,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /** Get the mail representation of the notification. */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('messages.operations_digest.subject', ['organization' => $this->organization->title]))
            ->greeting(__('messages.operations_digest.greeting'))
            ->line(__('messages.operations_digest.introduction', ['count' => $this->actionItems->count()]));

        $this->actionItems->take(10)->each(function (ActionItem $item) use ($message): void {
            $message->line('• ' . $item->title . ($item->due_on ? ' — ' . $item->due_on->toDateString() : ''));
        });

        return $message
            ->action(
                __('messages.operations_digest.action'),
                rtrim((string) config('app.frontend_url'), '/') . '/tasks'
            )
            ->line(__('messages.operations_digest.footer'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organization->id,
            'title' => __('messages.operations_digest.notification_title'),
            'count' => $this->actionItems->count(),
            'action_items' => $this->actionItems->take(20)->map(fn (ActionItem $item): array => [
                'id' => $item->id,
                'type' => $item->type->value,
                'title' => $item->title,
                'due_on' => $item->due_on?->toDateString(),
            ])->values()->all(),
        ];
    }
}
