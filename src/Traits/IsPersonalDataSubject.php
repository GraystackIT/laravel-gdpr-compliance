<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Traits;

use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Database\Eloquent\Builder;

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
            ->where('subject_id', $this->getKey())
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
        return $query->whereExists(function ($q) {
            $q->select('id')
                ->from('gdpr_deletions')
                ->whereColumn('gdpr_deletions.subject_id', $this->getTable().'.'.$this->getKeyName())
                ->where('gdpr_deletions.subject_type', static::class)
                ->whereIn('gdpr_deletions.state', ['pending_grace', 'pending_legal_hold']);
        });
    }

    /**
     * Query scope: subjects WITHOUT a pending deletion.
     */
    public function scopeWhereNotDeletionPending(Builder $query): Builder
    {
        return $query->whereNotExists(function ($q) {
            $q->select('id')
                ->from('gdpr_deletions')
                ->whereColumn('gdpr_deletions.subject_id', $this->getTable().'.'.$this->getKeyName())
                ->where('gdpr_deletions.subject_type', static::class)
                ->whereIn('gdpr_deletions.state', ['pending_grace', 'pending_legal_hold']);
        });
    }
}
