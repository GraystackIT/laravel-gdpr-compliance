<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GdprAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gdpr_audits';

    protected $guarded = [];

    protected $casts = [
        'affected_rows' => 'integer',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
