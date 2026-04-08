<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Consent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'consents';

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
