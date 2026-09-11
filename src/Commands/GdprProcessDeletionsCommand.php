<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Support\DeletionScheduler;
use Illuminate\Console\Command;

class GdprProcessDeletionsCommand extends Command
{
    protected $signature = 'gdpr:process-deletions';

    protected $description = 'Process due deletions: pass 1 (grace expired) and pass 2 (legal hold expired).';

    public function handle(DeletionScheduler $scheduler): int
    {
        $result = $scheduler->processDueDeletions();

        $this->info(sprintf(
            'Processed %d rows (pass 1) and %d rows (pass 2).',
            $result['pass1'],
            $result['pass2'],
        ));

        if ($result['deferred'] > 0) {
            $this->error(sprintf(
                'Left %d row(s) pending: their subject exists but no registered scope reaches it. '
                .'See the log and the deletion_deferred entries in gdpr_audits.',
                $result['deferred'],
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
