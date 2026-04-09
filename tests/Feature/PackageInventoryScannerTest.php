<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Support\PackageInventoryScanner;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->fixtures = __DIR__.'/../fixtures/lockfiles';
});

it('reads composer.lock with description and homepage', function () {
    $scanner = new PackageInventoryScanner($this->fixtures);
    $result = $scanner->scan();

    expect($result['composer'])->toHaveCount(2);

    $laravel = collect($result['composer'])->firstWhere('name', 'laravel/framework');
    expect($laravel['version'])->toBe('v13.0.0')
        ->and($laravel['description'])->toBe('The Laravel Framework.')
        ->and($laravel['homepage'])->toBe('https://laravel.com');
});

it('reads package-lock.json enriched from node_modules package.json', function () {
    $scanner = new PackageInventoryScanner($this->fixtures);
    $result = $scanner->scan();

    expect($result['npm'])->toHaveCount(2);

    $tailwind = collect($result['npm'])->firstWhere('name', 'tailwindcss');
    expect($tailwind['version'])->toBe('4.0.0')
        ->and($tailwind['description'])->toContain('utility-first')
        ->and($tailwind['homepage'])->toBe('https://tailwindcss.com');

    $alpine = collect($result['npm'])->firstWhere('name', 'alpinejs');
    expect($alpine['homepage'])->toContain('github.com/alpinejs/alpine'); // fallback to repository.url
});

it('writes the payload as JSON to the configured path', function () {
    $scanner = new PackageInventoryScanner($this->fixtures, outputPath: 'gdpr/inventory.json');
    $scanner->scan();

    Storage::disk('local')->assertExists('gdpr/inventory.json');
    $content = json_decode(Storage::disk('local')->get('gdpr/inventory.json'), true);

    expect($content)->toHaveKey('generated_at')
        ->and($content)->toHaveKey('composer')
        ->and($content)->toHaveKey('npm');
});

it('returns empty arrays when lockfiles are absent', function () {
    $scanner = new PackageInventoryScanner(__DIR__.'/../fixtures/does-not-exist');
    $result = $scanner->scan();

    expect($result['composer'])->toBe([])
        ->and($result['npm'])->toBe([]);
});

it('sorts packages alphabetically for stable diffs', function () {
    $scanner = new PackageInventoryScanner($this->fixtures);
    $result = $scanner->scan();

    $names = array_column($result['composer'], 'name');
    expect($names)->toBe(['graystackit/laravel-gdpr-compliance', 'laravel/framework']);

    $npmNames = array_column($result['npm'], 'name');
    expect($npmNames)->toBe(['alpinejs', 'tailwindcss']);
});
