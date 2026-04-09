<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Anonymizers\AddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\EmailAnonymizer;
use GraystackIt\Gdpr\Anonymizers\FreeTextAnonymizer;
use GraystackIt\Gdpr\Anonymizers\IpAddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\NameAnonymizer;
use GraystackIt\Gdpr\Anonymizers\PhoneAnonymizer;
use GraystackIt\Gdpr\Anonymizers\StaticTextAnonymizer;

it('NameAnonymizer replaces with placeholder and preserves null', function () {
    $a = new NameAnonymizer;

    expect($a->anonymize('John Doe'))->toBe('Anonymous User')
        ->and($a->anonymize('John', ['placeholder' => 'N/N']))->toBe('N/N')
        ->and($a->anonymize(null))->toBeNull();
});

it('EmailAnonymizer produces an invalid-domain placeholder', function () {
    $a = new EmailAnonymizer;

    $result = $a->anonymize('user@example.com');

    expect($result)->toStartWith('anonymized_')
        ->and($result)->toEndWith('@example.invalid');

    $custom = $a->anonymize('x@y', ['domain' => 'wiped.local']);
    expect($custom)->toEndWith('@wiped.local');

    expect($a->anonymize(null))->toBeNull();
});

it('PhoneAnonymizer replaces with placeholder', function () {
    $a = new PhoneAnonymizer;

    expect($a->anonymize('+43 664 1234567'))->toBe('+00 000 0000000')
        ->and($a->anonymize('+43', ['placeholder' => 'REDACTED']))->toBe('REDACTED');
});

it('IpAddressAnonymizer masks IPv4 per config', function () {
    $a = new IpAddressAnonymizer;

    expect($a->anonymize('192.168.1.42'))->toBe('192.168.1.0') // default octet
        ->and($a->anonymize('192.168.1.42', ['mask' => 'half']))->toBe('192.168.0.0')
        ->and($a->anonymize('192.168.1.42', ['mask' => 'full']))->toBe('0.0.0.0');
});

it('IpAddressAnonymizer masks IPv6', function () {
    $a = new IpAddressAnonymizer;

    $result = $a->anonymize('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

    expect($result)->toContain('2001:')->toContain(':0:0:0:0');
});

it('IpAddressAnonymizer returns null for invalid input', function () {
    $a = new IpAddressAnonymizer;

    expect($a->anonymize('not-an-ip'))->toBeNull()
        ->and($a->anonymize(null))->toBeNull();
});

it('AddressAnonymizer redacts strings and flat arrays', function () {
    $a = new AddressAnonymizer;

    expect($a->anonymize('Main St 1, 1010 Vienna'))->toBe('[REDACTED ADDRESS]')
        ->and($a->anonymize(['street' => 'Main St 1', 'city' => 'Vienna']))
        ->toBe(['street' => '[REDACTED]', 'city' => '[REDACTED]']);
});

it('AddressAnonymizer redacts nested arrays recursively', function () {
    $a = new AddressAnonymizer;

    $nested = [
        'billing' => ['street' => 'Main St 1', 'city' => 'Vienna'],
        'shipping' => ['street' => 'Side St 2', 'zip' => '1010'],
    ];

    $result = $a->anonymize($nested);

    expect($result)->toBe([
        'billing' => ['street' => '[REDACTED]', 'city' => '[REDACTED]'],
        'shipping' => ['street' => '[REDACTED]', 'zip' => '[REDACTED]'],
    ]);
});

it('FreeTextAnonymizer replaces whole text by default', function () {
    $a = new FreeTextAnonymizer;

    expect($a->anonymize('Contact me at john@example.com'))->toBe('[REDACTED]');
});

it('FreeTextAnonymizer selectively replaces email/phone/urls', function () {
    $a = new FreeTextAnonymizer;

    $input = 'Mail john@example.com or call +43 664 1234567 visit https://example.com';
    $result = $a->anonymize($input, [
        'replace_email' => true,
        'replace_phone' => true,
        'replace_urls' => true,
    ]);

    expect($result)->toContain('[EMAIL]')
        ->toContain('[PHONE]')
        ->toContain('[URL]')
        ->not->toContain('john@example.com')
        ->not->toContain('example.com/'); // url stripped
});

it('StaticTextAnonymizer returns configured value', function () {
    $a = new StaticTextAnonymizer;

    expect($a->anonymize('anything'))->toBe('[REDACTED]')
        ->and($a->anonymize('anything', ['value' => '[ANONYMIZED]']))->toBe('[ANONYMIZED]');
});
