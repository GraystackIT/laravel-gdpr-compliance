<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Jobs;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ForceDeleteAfterLegalHoldJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $deletionId) {}

    public function handle(DeletionScheduler $scheduler): void
    {
        /** @var GdprDeletion|null $row */
        $row = GdprDeletion::find($this->deletionId);

        if ($row === null || $row->state !== DeletionState::PendingLegalHold) {
            return;
        }

        // Use the public runner which handles the force delete lifecycle.
        $scheduler->processDueDeletions();
    }
}
