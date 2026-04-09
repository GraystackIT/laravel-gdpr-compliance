<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Support\PackageInventoryScanner;
use Illuminate\Console\Command;

class GdprPackagesScanCommand extends Command
{
    protected $signature = 'gdpr:packages-scan';

    protected $description = 'Scan composer.lock and package-lock.json and write the package inventory JSON.';

    public function handle(PackageInventoryScanner $scanner): int
    {
        $payload = $scanner->scan();

        $this->info(sprintf(
            'Inventory scan complete: %d composer packages, %d npm packages.',
            count($payload['composer']),
            count($payload['npm']),
        ));

        return self::SUCCESS;
    }
}
