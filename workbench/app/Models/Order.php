<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Order extends Model implements PersonalData
{
    protected $table = 'orders';

    protected $guarded = [];

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('billing_email')->anonymizeWith('email')->exportable()
            ->field('shipping_address')->anonymizeWith('address')->exportable()
            ->field('total')->exportable()
            ->retention(
                mode: RetentionMode::LegalHold,
                legalHoldDays: 3650,
                legalBasis: '§ 147 AO — tax record retention',
            )
            ->processOrder(100);
    }

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof User => $query->where('user_id', $subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
