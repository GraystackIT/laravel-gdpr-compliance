<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Scans the host app's composer.lock and package-lock.json (if present)
 * and writes a normalized JSON snapshot.
 *
 * Composer: description and homepage come directly from composer.lock.
 * NPM: lockfile only carries name+version, so the scanner reads each
 * package's package.json from node_modules/ to enrich description and
 * homepage. If node_modules is missing, npm entries fall back to just
 * name+version and the extra fields become null.
 */
class PackageInventoryScanner
{
    public function __construct(
        protected string $basePath,
        protected string $outputDisk = 'local',
        protected string $outputPath = 'gdpr/package-inventory.json',
    ) {}

    /**
     * Run the scan and write the JSON file. Returns the resulting payload.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $payload = [
            'generated_at' => now()->toIso8601String(),
            'composer' => $this->readComposer(),
            'npm' => $this->readNpm(),
        ];

        Storage::disk($this->outputDisk)->put(
            $this->outputPath,
            (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return $payload;
    }

    /**
     * @return array<int, array{name: string, version: string, description: ?string, homepage: ?string}>
     */
    protected function readComposer(): array
    {
        $path = $this->basePath.'/composer.lock';
        if (! is_file($path)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($path), true);
        if (! is_array($lock) || ! isset($lock['packages']) || ! is_array($lock['packages'])) {
            return [];
        }

        $packages = [];
        foreach ($lock['packages'] as $pkg) {
            if (! isset($pkg['name'])) {
                continue;
            }
            $packages[] = [
                'name' => (string) $pkg['name'],
                'version' => (string) ($pkg['version'] ?? ''),
                'description' => $pkg['description'] ?? null,
                'homepage' => $pkg['homepage'] ?? null,
            ];
        }

        usort($packages, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $packages;
    }

    /**
     * @return array<int, array{name: string, version: string, description: ?string, homepage: ?string}>
     */
    protected function readNpm(): array
    {
        $path = $this->basePath.'/package-lock.json';
        if (! is_file($path)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($path), true);
        if (! is_array($lock)) {
            return [];
        }

        $entries = [];

        // package-lock.json v2/v3 uses 'packages' keyed by path (e.g. "node_modules/react").
        if (isset($lock['packages']) && is_array($lock['packages'])) {
            foreach ($lock['packages'] as $path => $pkg) {
                // Skip the root entry (empty string key)
                if ($path === '' || ! str_starts_with($path, 'node_modules/')) {
                    continue;
                }
                if (! is_array($pkg) || ($pkg['dev'] ?? false) === true) {
                    continue;
                }
                $name = (string) ($pkg['name'] ?? substr($path, strlen('node_modules/')));
                $entries[$name] = [
                    'name' => $name,
                    'version' => (string) ($pkg['version'] ?? ''),
                    'description' => null,
                    'homepage' => null,
                ];
            }
        }

        // Enrich with description and homepage from node_modules/<pkg>/package.json when present.
        foreach ($entries as $name => &$entry) {
            $manifestPath = $this->basePath.'/node_modules/'.$name.'/package.json';
            if (! is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifest)) {
                continue;
            }
            $entry['description'] = isset($manifest['description']) ? (string) $manifest['description'] : null;
            $entry['homepage'] = $this->resolveHomepage($manifest);
        }
        unset($entry);

        $entries = array_values($entries);
        usort($entries, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    protected function resolveHomepage(array $manifest): ?string
    {
        if (! empty($manifest['homepage']) && is_string($manifest['homepage'])) {
            return $manifest['homepage'];
        }

        if (isset($manifest['repository'])) {
            if (is_string($manifest['repository'])) {
                return $manifest['repository'];
            }
            if (is_array($manifest['repository']) && ! empty($manifest['repository']['url'])) {
                return (string) $manifest['repository']['url'];
            }
        }

        return null;
    }
}
