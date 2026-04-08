<?php

declare(strict_types=1);

return [

    'events' => [
        'deletion_requested' => 'Personal data deletion requested',
        'deletion_scheduled' => 'Personal data deletion scheduled for model :model',
        'deletion_cancelled' => 'Personal data deletion cancelled',
        'anonymization_completed' => 'Personal data anonymized on :model (:rows rows)',
        'deletion_completed' => 'Personal data erased on :model (:rows rows)',
        'legal_hold_started' => 'Legal hold started on :model until :until',
        'legal_hold_expired' => 'Legal hold expired on :model, rows force-deleted',
        'export_requested' => 'Personal data export requested',
        'export_completed' => 'Personal data export completed',
    ],

    'notifications' => [
        'deletion_requested' => [
            'subject' => 'Your data deletion request has been received',
            'greeting' => 'Hello,',
            'body' => 'We have received your request to delete your personal data. Processing will begin on :scheduled_for.',
            'grace_notice' => 'You have until :scheduled_for to cancel this request.',
            'cancel_button' => 'Cancel deletion request',
            'closing' => 'If you did not request this, please contact support immediately.',
            'salutation' => 'Thank you,',
        ],
        'deletion_cancelled' => [
            'subject' => 'Your data deletion request has been cancelled',
            'greeting' => 'Hello,',
            'body' => 'Your personal data deletion request has been cancelled. Your account and data remain unchanged.',
            'salutation' => 'Thank you,',
        ],
        'deletion_completed' => [
            'subject' => 'Your personal data has been processed',
            'greeting' => 'Hello,',
            'body' => 'We have completed the processing of your personal data request. This is the last email you will receive from us in connection with this request.',
            'salutation' => 'Regards,',
        ],
        'export_ready' => [
            'subject' => 'Your personal data export is ready',
            'greeting' => 'Hello,',
            'body' => 'Your personal data export has been prepared. You can download it using the link below.',
            'download_button' => 'Download your data',
            'expiry_notice' => 'This download link will expire on :expires_at.',
            'salutation' => 'Regards,',
        ],
    ],

    'middleware' => [
        'consent_required' => 'Access denied: the purpose ":purpose" requires consent that has not been granted.',
        'deletion_pending' => 'Access denied: a data deletion request is pending for this account.',
    ],

    'cookie_banner' => [
        'title' => 'We use cookies',
        'description' => 'We use cookies and similar technologies to provide our services, personalize content and analyze traffic.',
        'accept_all' => 'Accept all',
        'reject_all' => 'Reject all',
        'customize' => 'Customize',
        'save_preferences' => 'Save preferences',
    ],

];
