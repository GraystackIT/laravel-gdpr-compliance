<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Traits;

use Closure;
use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\SubjectKeyType;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Gives a model the ability to initiate GDPR requests on itself.
 *
 * Models that use this trait can call requestDeletion, requestExport,
 * cancelDeletion, deleteImmediately, and check isDeletionPending. It
 * also adds query scopes to filter by pending-deletion state.
 */
trait IsPersonalDataSubject
{
    public function requestDeletion(): GdprRequest
    {
        return app(GdprManager::class)->requestDeletion($this);
    }

    public function deleteImmediately(): GdprRequest
    {
        return app(GdprManager::class)->deleteImmediately($this);
    }

    public function requestExport(): GdprRequest
    {
        return app(GdprManager::class)->requestExport($this);
    }

    public function cancelDeletion(): void
    {
        $pending = GdprRequest::query()
            ->where('subject_type', static::class)
            ->where('subject_id', SubjectKeyType::of($this))
            ->whereIn('status', ['pending', 'processing'])
            ->latest('requested_at')
            ->first();

        if ($pending !== null) {
            app(GdprManager::class)->cancelDeletion($pending);
        }
    }

    public function isDeletionPending(): bool
    {
        return app(GdprManager::class)->isDeletionPending($this);
    }

    /**
     * Query scope: subjects with a pending deletion (grace or legal hold).
     */
    public function scopeWhereDeletionPending(Builder $query): Builder
    {
        return $query->whereExists($this->pendingDeletionExists());
    }

    /**
     * Query scope: subjects WITHOUT a pending deletion.
     */
    public function scopeWhereNotDeletionPending(Builder $query): Builder
    {
        return $query->whereNotExists($this->pendingDeletionExists());
    }

    /**
     * The correlated subquery behind both deletion-pending scopes. It has to
     * match `subject_id` against the subject's key column, and the two are
     * only the same SQL type for a bigint installation — SubjectKeyType knows
     * how the column is defined and compares accordingly.
     *
     * @return Closure(QueryBuilder): void
     */
    protected function pendingDeletionExists(): Closure
    {
        $keyColumn = $this->getTable().'.'.$this->getKeyName();

        return function (QueryBuilder $query) use ($keyColumn): void {
            $query->select('id')
                ->from('gdpr_deletions')
                ->where('gdpr_deletions.subject_type', static::class)
                ->whereIn('gdpr_deletions.state', [
                    DeletionState::PendingGrace->value,
                    DeletionState::PendingLegalHold->value,
                ]);

            SubjectKeyType::configured()->whereSubjectIdMatchesKey(
                $query,
                'gdpr_deletions.subject_id',
                $keyColumn,
            );
        };
    }
}
