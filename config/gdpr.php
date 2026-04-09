<?php

declare(strict_types=1);
use GraystackIt\Gdpr\Anonymizers\AddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\EmailAnonymizer;
use GraystackIt\Gdpr\Anonymizers\FreeTextAnonymizer;
use GraystackIt\Gdpr\Anonymizers\IpAddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\NameAnonymizer;
use GraystackIt\Gdpr\Anonymizers\PhoneAnonymizer;
use GraystackIt\Gdpr\Anonymizers\StaticTextAnonymizer;

return [

    /*
    |--------------------------------------------------------------------------
    | Registered models
    |--------------------------------------------------------------------------
    |
    | List all models that contain personal data. Each must implement
    | GraystackIt\Gdpr\Contracts\PersonalData and provide a
    | personalData(PersonalDataBlueprint) method.
    |
    | Models can be registered simply (self-describing):
    |   \App\Models\User::class,
    |
    | Or with external profile and scope for vendor models:
    |   \Vendor\Pkg\Thing::class => [
    |       'profile' => \App\Gdpr\Profiles\ThingProfile::class,
    |       'scope'   => \App\Gdpr\Scopes\ThingScope::class,
    |   ],
    |
    */

    'models' => [
        // \App\Models\User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Anonymizer aliases
    |--------------------------------------------------------------------------
    |
    | Maps aliases (used in ->anonymizeWith('alias')) to FQCN.
    | Add custom anonymizers here under your own alias.
    |
    */

    'anonymizers' => [
        'name' => NameAnonymizer::class,
        'email' => EmailAnonymizer::class,
        'phone' => PhoneAnonymizer::class,
        'ip_address' => IpAddressAnonymizer::class,
        'address' => AddressAnonymizer::class,
        'free_text' => FreeTextAnonymizer::class,
        'static_text' => StaticTextAnonymizer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Each key maps to a notification class. Use null for the package default,
    | false to disable, or an FQCN to override with your own class.
    |
    */

    'notifications' => [
        'enabled' => true,
        'deletion_requested' => null,
        'deletion_cancelled' => null,
        'deletion_completed' => null,
        'export_ready' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention & pruning
    |--------------------------------------------------------------------------
    |
    | Time-based retention for append-only tables. The gdpr:prune command uses
    | these. Defaults per § 195 BGB / § 1489 ABGB: 3 years (1095 days).
    |
    */

    'retention' => [
        'audits_days' => 1095,
        'consents_days' => 1095,
        'policy_acceptances_days' => 1095,
        'notification_email_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy links
    |--------------------------------------------------------------------------
    |
    | URLs or named routes to policy / imprint pages.
    |
    */

    'policies' => [
        'privacy' => ['url' => null, 'route' => null],
        'imprint' => ['url' => null, 'route' => null],
        'tos' => ['url' => null, 'route' => null],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'disk' => 'local',
        'path_prefix' => 'gdpr/exports',
        'expires_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package inventory
    |--------------------------------------------------------------------------
    */

    'inventory' => [
        'disk' => 'local',
        'path' => 'gdpr/package-inventory.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'connection' => null, // null = default connection
        'queue' => null,      // null = default queue
    ],

];
