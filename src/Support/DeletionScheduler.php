<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Events\LegalHoldExpired;
use GraystackIt\Gdpr\Events\LegalHoldStarted;
use GraystackIt\Gdpr\Events\PersonalDataAnonymized;
use GraystackIt\Gdpr\Events\PersonalDataDeletionCancelled;
use GraystackIt\Gdpr\Events\PersonalDataDeletionRequested;
use GraystackIt\Gdpr\Events\PersonalDataErased;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;

/**
 * Orchestrates the deletion lifecycle:
 *
 *  - requestDeletion: walks the registry, creates one gdpr_deletions row
 *    per affected model (plus the subject itself), snapshots retention,
 *    and logs per-model deletion_scheduled audit entries.
 *
 *  - cancelDeletion: marks all pending_grace rows for a request as cancelled.
 *
 *  - processDueDeletions: picks up rows whose scheduled_for has passed and
 *    (for Pass 2) rows whose hold_until has passed, and processes them.
 *
 * Actual wiping of rows is delegated to PersonalDataEraser.
 */
class DeletionScheduler
{
    public function __construct(
        protected ModelRegistry $registry,
        protected SubjectRecordResolver $resolver,
        protected PersonalDataEraser $eraser,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Schedule a deletion for the given subject. Returns the created request.
     */
    public function requestDeletion(Model $subject): GdprRequest
    {
        if (! $this->registry->has($subject::class)) {
            throw new LogicException(sprintf(
                'Subject class %s is not registered in config("gdpr.models")',
                $subject::class,
            ));
        }

        return DB::transaction(function () use ($subject) {
            $subjectBlueprint = $this->registry->blueprintFor($subject::class);
            $subjectPolicy = $subjectBlueprint->retentionPolicy();

            $now = now();
            $scheduledFor = $subjectPolicy->hasGrace()
                ? $now->copy()->addDays($subjectPolicy->gracePeriodDays)
                : $now->copy();

            $request = GdprRequest::create([
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'type' => RequestType::Delete,
                'status' => RequestStatus::Pending,
                'notification_email' => $subject->getAttribute('email'),
                'requested_at' => $now,
            ]);

            $registryModels = [];

            foreach ($this->registry->all() as $modelClass) {
                if (! class_exists($modelClass)) {
                    continue;
                }

                // Count affected rows via the subject scope (or primary key for the subject itself).
                $instance = new $modelClass;
                $query = $instance->newQuery();

                if ($modelClass === $subject::class) {
                    $query->whereKey($subject->getKey());
                } else {
                    $query = $this->registry->applyScopeFor($modelClass, $query, $subject);
                }

                $count = (int) $query->count();
                if ($count === 0) {
                    continue;
                }

                $blueprint = $this->registry->blueprintFor($modelClass);
                $policy = $blueprint->retentionPolicy();

                GdprDeletion::create([
                    'gdpr_request_id' => $request->id,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'target_model' => $modelClass,
                    'retention_snapshot' => $policy->toSnapshot(),
                    'state' => DeletionState::PendingGrace,
                    'process_order' => $blueprint->getProcessOrder(),
                    'scheduled_for' => $scheduledFor,
                ]);

                $this->auditLogger->log(
                    event: 'deletion_scheduled',
                    subject: $subject,
                    targetModel: $modelClass,
                    affectedRows: $count,
                    context: [
                        'retention_snapshot' => $policy->toSnapshot(),
                        'process_order' => $blueprint->getProcessOrder(),
                    ],
                );

                $registryModels[] = $modelClass;
            }

            $this->auditLogger->log(
                event: 'deletion_requested',
                subject: $subject,
                context: [
                    'registered_models_snapshot' => $registryModels,
                    'grace_period_days' => $subjectPolicy->gracePeriodDays,
                    'scheduled_for' => $scheduledFor->toIso8601String(),
                ],
            );

            Event::dispatch(new PersonalDataDeletionRequested($request));

            return $request;
        });
    }

    /**
     * Cancel a pending deletion during the grace window.
     */
    public function cancelDeletion(GdprRequest $request): void
    {
        if ($request->status->isTerminal()) {
            throw new LogicException('Cannot cancel a request that is already terminal.');
        }

        DB::transaction(function () use ($request) {
            $rows = GdprDeletion::query()
                ->where('gdpr_request_id', $request->id)
                ->where('state', DeletionState::PendingGrace)
                ->get();

            foreach ($rows as $row) {
                $row->transitionTo(DeletionState::Cancelled);
                $row->processed_at = now();
                $row->save();
            }

            $request->status = RequestStatus::Cancelled;
            $request->completed_at = now();
            $request->save();

            $this->auditLogger->log(
                event: 'deletion_cancelled',
                subjectType: $request->subject_type,
                subjectId: (int) $request->subject_id,
            );

            Event::dispatch(new PersonalDataDeletionCancelled($request));
        });
    }

    /**
     * Process all rows whose grace period has expired (Pass 1) and all rows
     * whose legal hold has expired (Pass 2).
     *
     * Rows are processed grouped by request, sorted by process_order ASC.
     *
     * @return array{pass1: int, pass2: int}
     */
    public function processDueDeletions(): array
    {
        $pass1 = $this->runGraceExpiredPass();
        $pass2 = $this->runLegalHoldExpiredPass();

        return ['pass1' => $pass1, 'pass2' => $pass2];
    }

    protected function runGraceExpiredPass(): int
    {
        $requestIds = GdprDeletion::query()
            ->where('state', DeletionState::PendingGrace)
            ->where('scheduled_for', '<=', now())
            ->distinct()
            ->pluck('gdpr_request_id');

        $total = 0;

        foreach ($requestIds as $requestId) {
            $rows = GdprDeletion::query()
                ->where('gdpr_request_id', $requestId)
                ->where('state', DeletionState::PendingGrace)
                ->orderBy('process_order')
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                $this->processSubjectDeletionRow($row);
                $total++;
            }

            $this->maybeMarkRequestCompleted((int) $requestId);
        }

        return $total;
    }

    protected function runLegalHoldExpiredPass(): int
    {
        $rows = GdprDeletion::query()
            ->where('state', DeletionState::PendingLegalHold)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', now())
            ->orderBy('gdpr_request_id')
            ->orderBy('process_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $this->forceDeleteAfterLegalHold($row);
            $this->maybeMarkRequestCompleted((int) $row->gdpr_request_id);
        }

        return $rows->count();
    }

    /**
     * Process a single gdpr_deletions row in state pending_grace.
     * Dispatches the right action based on the retention snapshot mode.
     */
    public function processSubjectDeletionRow(GdprDeletion $row): void
    {
        if ($row->state !== DeletionState::PendingGrace) {
            return; // idempotent
        }

        $policy = $row->retentionPolicy();
        $subject = $this->loadSubject($row);
        $targetModel = $row->target_model;

        if ($subject === null) {
            // Subject is gone already; mark erased.
            $row->transitionTo(DeletionState::Erased);
            $row->processed_at = now();
            $row->save();

            return;
        }

        $rows = $this->rowsForTarget($targetModel, $subject);
        $affected = $rows->count();

        match ($policy->mode) {
            RetentionMode::Delete => $this->handleDelete($row, $rows, $subject, $targetModel, $affected),
            RetentionMode::Anonymize => $this->handleAnonymize($row, $rows, $subject, $targetModel, $affected),
            RetentionMode::LegalHold => $this->handleLegalHold($row, $rows, $policy, $subject, $targetModel, $affected),
        };
    }

    /**
     * Handle force-deletion of rows in pending_legal_hold past hold_until.
     */
    protected function forceDeleteAfterLegalHold(GdprDeletion $row): void
    {
        if ($row->state !== DeletionState::PendingLegalHold) {
            return;
        }

        // The subject may be long gone by the time the legal hold expires.
        // Construct a ghost subject carrying only the primary key so the
        // scope can still filter rows by the original FK.
        $subject = $this->loadSubject($row) ?? $this->ghostSubject($row);
        if ($subject !== null) {
            $rows = $this->rowsForTarget($row->target_model, $subject);
            $this->eraser->deleteRows($rows);
        }

        $row->transitionTo(DeletionState::Erased);
        $row->processed_at = now();
        $row->save();

        $this->auditLogger->log(
            event: 'legal_hold_expired',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $row->target_model,
        );
        $this->auditLogger->log(
            event: 'deletion_completed',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $row->target_model,
        );

        Event::dispatch(new LegalHoldExpired($row));
        Event::dispatch(new PersonalDataErased($row));
    }

    /**
     * @param  Collection<int, Model>  $rows
     */
    protected function handleDelete(GdprDeletion $row, $rows, Model $subject, string $targetModel, int $affected): void
    {
        $this->eraser->deleteRows($rows);

        $row->transitionTo(DeletionState::Erased);
        $row->processed_at = now();
        $row->save();

        $this->auditLogger->log(
            event: 'deletion_completed',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $targetModel,
            affectedRows: $affected,
            context: ['mode' => 'delete'],
        );

        Event::dispatch(new PersonalDataErased($row));
    }

    /**
     * @param  Collection<int, Model>  $rows
     */
    protected function handleAnonymize(GdprDeletion $row, $rows, Model $subject, string $targetModel, int $affected): void
    {
        $count = $this->eraser->eraseRows($targetModel, $rows);

        $row->transitionTo(DeletionState::Anonymized);
        $row->processed_at = now();
        $row->save();

        $this->auditLogger->log(
            event: 'anonymization_completed',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $targetModel,
            affectedRows: $count,
            context: ['mode' => 'anonymize'],
        );

        Event::dispatch(new PersonalDataAnonymized($row));
    }

    /**
     * @param  Collection<int, Model>  $rows
     */
    protected function handleLegalHold(GdprDeletion $row, $rows, RetentionPolicy $policy, Model $subject, string $targetModel, int $affected): void
    {
        $count = $this->eraser->eraseRows($targetModel, $rows);

        $row->transitionTo(DeletionState::PendingLegalHold);
        $row->hold_until = now()->addDays((int) $policy->legalHoldDays);
        $row->processed_at = now();
        $row->save();

        $this->auditLogger->log(
            event: 'anonymization_completed',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $targetModel,
            affectedRows: $count,
            context: ['mode' => 'legal_hold'],
        );
        $this->auditLogger->log(
            event: 'legal_hold_started',
            subjectType: $row->subject_type,
            subjectId: (int) $row->subject_id,
            targetModel: $targetModel,
            context: [
                'hold_until' => $row->hold_until->toIso8601String(),
                'legal_basis' => $policy->legalBasis,
            ],
        );

        Event::dispatch(new PersonalDataAnonymized($row));
        Event::dispatch(new LegalHoldStarted($row));
    }

    protected function loadSubject(GdprDeletion $row): ?Model
    {
        $class = $row->subject_type;
        if (! class_exists($class)) {
            return null;
        }

        /** @var Model|null $found */
        $found = $class::query()->find($row->subject_id);

        return $found;
    }

    /**
     * Construct a ghost subject carrying only the primary key. Used when the
     * real subject is already gone (e.g. during Pass 2 after the subject was
     * force-deleted in Pass 1) but related rows still need to be scoped.
     */
    protected function ghostSubject(GdprDeletion $row): ?Model
    {
        $class = $row->subject_type;
        if (! class_exists($class)) {
            return null;
        }

        /** @var Model $ghost */
        $ghost = new $class;
        $ghost->setAttribute($ghost->getKeyName(), $row->subject_id);

        // exists = false by default; the scope only reads getKey() so this works.
        return $ghost;
    }

    /**
     * @return Collection<int, Model>
     */
    protected function rowsForTarget(string $targetModel, Model $subject): Collection
    {
        if (! class_exists($targetModel)) {
            /** @var Collection<int, Model> $empty */
            $empty = new Collection;

            return $empty;
        }

        $instance = new $targetModel;
        $query = $instance->newQuery();

        if ($targetModel === $subject::class) {
            $query->whereKey($subject->getKey());
        } else {
            $query = $this->registry->applyScopeFor($targetModel, $query, $subject);
        }

        return $query->get();
    }

    /**
     * If every row for a request is terminal, mark the request completed.
     */
    protected function maybeMarkRequestCompleted(int $requestId): void
    {
        $open = GdprDeletion::query()
            ->where('gdpr_request_id', $requestId)
            ->whereIn('state', [
                DeletionState::PendingGrace->value,
                DeletionState::PendingLegalHold->value,
            ])
            ->count();

        if ($open > 0) {
            return;
        }

        GdprRequest::query()->whereKey($requestId)->update([
            'status' => RequestStatus::Completed->value,
            'completed_at' => now(),
        ]);
    }
}
