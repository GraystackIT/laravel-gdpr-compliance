<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Enums\SubjectKeyType;
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
use Illuminate\Support\Facades\Log;
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
    /**
     * Rows the current processDueDeletions() run had to leave pending.
     */
    protected int $deferred = 0;

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
                'subject_id' => SubjectKeyType::of($subject),
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

                // Count affected rows via the subject scope (which, for the
                // subject's own model, narrows to its primary key).
                $instance = new $modelClass;
                $query = $this->registry->applyScopeFor($modelClass, $instance->newQuery(), $subject);

                $count = (int) $query->count();
                if ($count === 0) {
                    // A subject the package cannot see would be scheduled
                    // without a row of its own and silently survive the
                    // deletion. Refuse before anything is written.
                    if ($modelClass === $subject::class && $this->subjectRowExists($instance, $subject->getKey())) {
                        throw SubjectNotReachable::for($modelClass, $subject->getKey());
                    }

                    continue;
                }

                $blueprint = $this->registry->blueprintFor($modelClass);
                $policy = $blueprint->retentionPolicy();

                GdprDeletion::create([
                    'gdpr_request_id' => $request->id,
                    'subject_type' => $subject::class,
                    'subject_id' => SubjectKeyType::of($subject),
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
                subjectId: $request->subject_id,
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
     * Rows whose subject exists but cannot be reached are left pending and
     * counted as deferred instead of being reported as processed.
     *
     * @return array{pass1: int, pass2: int, deferred: int}
     */
    public function processDueDeletions(): array
    {
        $this->deferred = 0;

        $pass1 = $this->runGraceExpiredPass();
        $pass2 = $this->runLegalHoldExpiredPass();

        return ['pass1' => $pass1, 'pass2' => $pass2, 'deferred' => $this->deferred];
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
                try {
                    $this->processSubjectDeletionRow($row);
                } catch (SubjectNotReachable $e) {
                    $this->defer($row, $e);

                    continue;
                }

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

        $total = 0;

        foreach ($rows as $row) {
            try {
                $this->forceDeleteAfterLegalHold($row);
            } catch (SubjectNotReachable $e) {
                $this->defer($row, $e);

                continue;
            }

            $this->maybeMarkRequestCompleted((int) $row->gdpr_request_id);
            $total++;
        }

        return $total;
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
        $targetModel = $row->target_model;

        // The subject can be gone before its grace period expires — deleted by
        // the application, by a cascading FK — while the rows of the other
        // models that belonged to it are still there, PII included. A ghost
        // subject carrying only the primary key keeps those reachable, the
        // same way Pass 2 reaches them after a legal hold.
        $subject = $this->loadSubject($row) ?? $this->ghostSubject($row);

        if ($subject === null) {
            // subject_type no longer resolves to a class.
            $this->markErased($row, 'subject_class_missing');

            return;
        }

        $rows = $this->rowsForTarget($targetModel, $subject);
        $affected = $rows->count();

        if ($affected === 0 && ! $subject->exists) {
            // Subject gone and nothing left of this model: the data is gone,
            // whatever the retention mode would have done with it.
            $this->markErased($row, 'no_rows_left');

            return;
        }

        match ($policy->mode) {
            RetentionMode::Delete => $this->handleDelete($row, $rows, $subject, $targetModel, $affected),
            RetentionMode::Anonymize => $this->handleAnonymize($row, $rows, $subject, $targetModel, $affected),
            RetentionMode::LegalHold => $this->handleLegalHold($row, $rows, $policy, $subject, $targetModel, $affected),
        };
    }

    /**
     * Close a row without touching any host rows: there are none left.
     *
     * The audit entry and the event still fire. A row reaching a terminal
     * state has to be readable from gdpr_audits, and listeners that clean up
     * copies of the data outside the database are due either way.
     */
    protected function markErased(GdprDeletion $row, string $reason): void
    {
        $row->transitionTo(DeletionState::Erased);
        $row->processed_at = now();
        $row->save();

        $this->auditLogger->log(
            event: 'deletion_completed',
            subjectType: $row->subject_type,
            subjectId: $row->subject_id,
            targetModel: $row->target_model,
            affectedRows: 0,
            context: ['reason' => $reason],
        );

        Event::dispatch(new PersonalDataErased($row));
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
            subjectId: $row->subject_id,
            targetModel: $row->target_model,
        );
        $this->auditLogger->log(
            event: 'deletion_completed',
            subjectType: $row->subject_type,
            subjectId: $row->subject_id,
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
            subjectId: $row->subject_id,
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
            subjectId: $row->subject_id,
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
            subjectId: $row->subject_id,
            targetModel: $targetModel,
            affectedRows: $count,
            context: ['mode' => 'legal_hold'],
        );
        $this->auditLogger->log(
            event: 'legal_hold_started',
            subjectType: $row->subject_type,
            subjectId: $row->subject_id,
            targetModel: $targetModel,
            context: [
                'hold_until' => $row->hold_until->toIso8601String(),
                'legal_basis' => $policy->legalBasis,
            ],
        );

        Event::dispatch(new PersonalDataAnonymized($row));
        Event::dispatch(new LegalHoldStarted($row));
    }

    /**
     * Load the subject of a deletion row. Returns null only when the row is
     * really gone — a subject that merely is not visible right now would end
     * up recorded as erased without anything having been erased.
     *
     * @throws SubjectNotReachable when the row exists but no scope reaches it
     */
    protected function loadSubject(GdprDeletion $row): ?Model
    {
        $class = $row->subject_type;
        if (! class_exists($class)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $class;
        $ghost = $this->keyOnlySubject(new $class, $row->subject_id);

        /** @var Model|null $found */
        $found = $this->registry->applyScopeFor($class, $instance->newQuery(), $ghost)->first();

        if ($found !== null) {
            return $found;
        }

        if ($this->subjectRowExists($instance, $row->subject_id)) {
            throw SubjectNotReachable::for($class, $row->subject_id);
        }

        return null;
    }

    /**
     * Whether the subject row is still in the table at all, ignoring every
     * scope. Only ever used to tell "gone" apart from "hidden" — never to
     * read or write rows the model's scopes keep out of reach.
     */
    protected function subjectRowExists(Model $instance, int|string $subjectId): bool
    {
        return $instance->newQueryWithoutScopes()->whereKey($subjectId)->exists();
    }

    /**
     * Leave an unreachable row pending and record why. The next run picks it
     * up again; a deletion that erased nothing must not report otherwise.
     */
    protected function defer(GdprDeletion $row, SubjectNotReachable $e): void
    {
        $this->deferred++;

        Log::error($e->getMessage(), [
            'gdpr_deletion_id' => $row->id,
            'subject_type' => $row->subject_type,
            'target_model' => $row->target_model,
        ]);

        $this->auditLogger->log(
            event: 'deletion_deferred',
            subjectType: $row->subject_type,
            subjectId: $row->subject_id,
            targetModel: $row->target_model,
            context: ['reason' => 'subject_not_reachable'],
        );
    }

    /**
     * Construct a ghost subject carrying only the primary key. Used when the
     * real subject is already gone (deleted by the application during grace,
     * or force-deleted in an earlier pass) but related rows still need to be
     * scoped.
     *
     * The primary key is all gdpr_deletions records of a subject, so this is
     * as much as the package can hand a scope. A subject scope that reads any
     * other attribute of the subject matches nothing here — hence the rule
     * that a subject scope filters on getKey() alone.
     */
    protected function ghostSubject(GdprDeletion $row): ?Model
    {
        $class = $row->subject_type;
        if (! class_exists($class)) {
            return null;
        }

        return $this->keyOnlySubject(new $class, $row->subject_id);
    }

    /**
     * A subject instance carrying nothing but its primary key. exists = false
     * by default; a subject scope only ever reads getKey(), so this is enough
     * to filter rows by it.
     */
    protected function keyOnlySubject(Model $instance, int|string $subjectId): Model
    {
        $instance->setAttribute($instance->getKeyName(), $subjectId);

        return $instance;
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

        return $this->registry
            ->applyScopeFor($targetModel, $instance->newQuery(), $subject)
            ->get();
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
