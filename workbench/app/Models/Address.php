<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Address extends Model implements PersonalData
{
    protected $table = 'addresses';

    protected $guarded = [];

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('line1')->anonymizeWith('address')->exportable()
            ->field('city')->anonymizeWith('address')->exportable()
            ->retention(mode: RetentionMode::Delete)
            ->processOrder(200);
    }

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof User => $query->where('user_id', $subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
