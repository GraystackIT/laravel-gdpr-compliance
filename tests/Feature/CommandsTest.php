<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Commands\GdprAuditCommand;
use GraystackIt\Gdpr\Commands\GdprCleanupExportsCommand;
use GraystackIt\Gdpr\Commands\GdprEraseCommand;
use GraystackIt\Gdpr\Commands\GdprExportCommand;
use GraystackIt\Gdpr\Commands\GdprPackagesScanCommand;
use GraystackIt\Gdpr\Commands\GdprProcessDeletionsCommand;
use GraystackIt\Gdpr\Commands\GdprPruneCommand;
use GraystackIt\Gdpr\Commands\GdprReportCommand;
use GraystackIt\Gdpr\Models\GdprAudit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;

// Register commands in the test environment
beforeEach(function () {
    $this->app[Kernel::class]->registerCommand(new GdprProcessDeletionsCommand);
    $this->app[Kernel::class]->registerCommand(new GdprExportCommand);
    $this->app[Kernel::class]->registerCommand(new GdprAuditCommand);
    $this->app[Kernel::class]->registerCommand(new GdprReportCommand);
    $this->app[Kernel::class]->registerCommand(new GdprCleanupExportsCommand);
    $this->app[Kernel::class]->registerCommand(new GdprPruneCommand);
    $this->app[Kernel::class]->registerCommand(new GdprPackagesScanCommand);
});

it('gdpr:report shows a status summary', function () {
    $this->artisan('gdpr:report')
        ->assertExitCode(0)
        ->expectsOutputToContain('GDPR activity summary');
});

it('gdpr:process-deletions runs both passes', function () {
    $this->artisan('gdpr:process-deletions')
        ->assertExitCode(0)
        ->expectsOutputToContain('Processed');
});

it('gdpr:audit shows audit entries', function () {
    GdprAudit::create([
        'subject_type' => 'App\\User',
        'subject_id' => 1,
        'event' => 'deletion_requested',
    ]);

    $this->artisan('gdpr:audit')
        ->assertExitCode(0)
        ->expectsOutputToContain('deletion_requested');
});

it('gdpr:packages-scan runs the inventory scanner', function () {
    Storage::fake('local');

    $this->artisan('gdpr:packages-scan')
        ->assertExitCode(0)
        ->expectsOutputToContain('Inventory scan complete');
});

it('gdpr:prune with --dry-run does not delete anything', function () {
    $this->artisan('gdpr:prune', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('Dry run');
});

it('gdpr:erase processes subject deletion', function () {
    $this->app[Kernel::class]
        ->registerCommand(new GdprEraseCommand);

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Address::create(['user_id' => $user->id, 'line1' => 'Main 1']);

    $this->artisan('gdpr:erase', [
        'subject' => User::class,
        'id' => $user->id,
        '--now' => true,
    ])->assertExitCode(0);

    expect(User::find($user->id))->toBeNull();
});
