<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Central orchestrator / facade target for the GDPR package.
 */
class GdprManager
{
    public function __construct(
        protected DeletionScheduler $scheduler,
        protected PersonalDataExporter $exporter,
        protected PackageInventoryScanner $inventoryScanner,
    ) {}

    public function requestDeletion(Model $subject): GdprRequest
    {
        return $this->scheduler->requestDeletion($subject);
    }

    public function cancelDeletion(GdprRequest $request): void
    {
        $this->scheduler->cancelDeletion($request);
    }

    public function deleteImmediately(Model $subject): GdprRequest
    {
        $request = $this->scheduler->requestDeletion($subject);

        // Move all pending_grace rows for this request to scheduled_for=now
        GdprDeletion::query()
            ->where('gdpr_request_id', $request->id)
            ->where('state', DeletionState::PendingGrace)
            ->update(['scheduled_for' => now()]);

        $this->scheduler->processDueDeletions();

        return $request->fresh() ?? $request;
    }

    public function processDueDeletions(): array
    {
        return $this->scheduler->processDueDeletions();
    }

    /**
     * Create an export request and return it. The actual file building is
     * delegated to the job (Phase 9) or runs synchronously via exporter.
     */
    public function requestExport(Model $subject): GdprRequest
    {
        return GdprRequest::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'type' => RequestType::Export,
            'status' => RequestStatus::Pending,
            'notification_email' => $subject->getAttribute('email'),
            'requested_at' => now(),
        ]);
    }

    /**
     * Check whether a deletion request is currently pending for the given subject.
     */
    public function isDeletionPending(Model $subject): bool
    {
        return GdprDeletion::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->whereIn('state', [
                DeletionState::PendingGrace->value,
                DeletionState::PendingLegalHold->value,
            ])
            ->exists();
    }

    /**
     * Return the package inventory as an array, reading the last generated
     * JSON file. Returns null if the file does not exist yet (run
     * `php artisan gdpr:packages-scan` to generate it).
     *
     * @return array<string, mixed>|null
     */
    public function packageInventory(): ?array
    {
        return $this->inventoryScanner->read();
    }
}
