<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GdprPolicyVersion extends Model
{
    protected $table = 'gdpr_policy_versions';

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function acceptances(): HasMany
    {
        return $this->hasMany(GdprPolicyAcceptance::class);
    }
}
