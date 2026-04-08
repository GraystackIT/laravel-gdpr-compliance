<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GdprPolicyAcceptance extends Model
{
    public const CREATED_AT = 'accepted_at';

    public const UPDATED_AT = null;

    protected $table = 'gdpr_policy_acceptances';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(GdprPolicyVersion::class, 'gdpr_policy_version_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
