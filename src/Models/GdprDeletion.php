<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Support\RetentionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class GdprDeletion extends Model
{
    protected $table = 'gdpr_deletions';

    protected $guarded = [];

    protected $casts = [
        'retention_snapshot' => 'array',
        'state' => DeletionState::class,
        'process_order' => 'integer',
        'scheduled_for' => 'datetime',
        'hold_until' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(GdprRequest::class, 'gdpr_request_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function retentionPolicy(): RetentionPolicy
    {
        return RetentionPolicy::fromSnapshot($this->retention_snapshot);
    }

    /**
     * Transition the state with validation. Throws if the target state
     * is not reachable from the current one.
     */
    public function transitionTo(DeletionState $next): void
    {
        if (! $this->state->canTransitionTo($next)) {
            throw new LogicException(sprintf(
                'Cannot transition GdprDeletion from %s to %s',
                $this->state->value,
                $next->value,
            ));
        }

        $this->state = $next;
    }
}
