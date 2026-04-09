<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Notifications;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalDataDeletionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public GdprRequest $request) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('gdpr::gdpr.notifications.deletion_requested.subject'))
            ->greeting(__('gdpr::gdpr.notifications.deletion_requested.greeting'))
            ->line(__('gdpr::gdpr.notifications.deletion_requested.body', [
                'scheduled_for' => optional($this->request->requested_at)->format('Y-m-d'),
            ]))
            ->line(__('gdpr::gdpr.notifications.deletion_requested.closing'))
            ->salutation(__('gdpr::gdpr.notifications.deletion_requested.salutation'));
    }
}
