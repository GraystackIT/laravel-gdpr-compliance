<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GdprRequest extends Model
{
    protected $table = 'gdpr_requests';

    protected $guarded = [];

    protected $casts = [
        'type' => RequestType::class,
        'status' => RequestStatus::class,
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'export_expires_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(GdprDeletion::class, 'gdpr_request_id');
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
