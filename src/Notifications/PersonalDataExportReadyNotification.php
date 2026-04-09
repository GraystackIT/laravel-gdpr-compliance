<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Notifications;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PersonalDataExportReadyNotification extends Notification
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
            ->subject(__('gdpr::gdpr.notifications.export_ready.subject'))
            ->greeting(__('gdpr::gdpr.notifications.export_ready.greeting'))
            ->line(__('gdpr::gdpr.notifications.export_ready.body'))
            ->line(__('gdpr::gdpr.notifications.export_ready.expiry_notice', [
                'expires_at' => optional($this->request->export_expires_at)->format('Y-m-d'),
            ]))
            ->salutation(__('gdpr::gdpr.notifications.export_ready.salutation'));
    }
}
