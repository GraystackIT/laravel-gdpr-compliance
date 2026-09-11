<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApplicantNote extends Model implements PersonalData
{
    protected $table = 'applicant_notes';

    protected $guarded = [];

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('body')->anonymizeWith('free_text')->exportable()
            ->retention(mode: RetentionMode::Anonymize)
            ->processOrder(100);
    }

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof Applicant => $query->where('applicant_id', $subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
