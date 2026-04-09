<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Notifications;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalDataDeletionCancelledNotification extends Notification
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
            ->subject(__('gdpr::gdpr.notifications.deletion_cancelled.subject'))
            ->greeting(__('gdpr::gdpr.notifications.deletion_cancelled.greeting'))
            ->line(__('gdpr::gdpr.notifications.deletion_cancelled.body'))
            ->salutation(__('gdpr::gdpr.notifications.deletion_cancelled.salutation'));
    }
}
