<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Console\Command;

class GdprReportCommand extends Command
{
    protected $signature = 'gdpr:report';

    protected $description = 'Show a summary of GDPR activity (requests, pending deletions, audit counts).';

    public function handle(): int
    {
        $this->info('GDPR activity summary');
        $this->line('');

        $this->line('Requests by status:');
        foreach (RequestStatus::cases() as $status) {
            $count = GdprRequest::where('status', $status)->count();
            $this->line(sprintf('  %-12s %d', $status->value, $count));
        }

        $this->line('');
        $this->line('Deletions by state:');
        foreach (DeletionState::cases() as $state) {
            $count = GdprDeletion::where('state', $state)->count();
            $this->line(sprintf('  %-20s %d', $state->value, $count));
        }

        $this->line('');
        $this->line(sprintf('Total consent records:  %d', Consent::count()));
        $this->line(sprintf('Total audit entries:    %d', GdprAudit::count()));

        return self::SUCCESS;
    }
}
