<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use GraystackIt\Gdpr\Traits\HasConsentRecords;
use GraystackIt\Gdpr\Traits\HasPersonalData;
use GraystackIt\Gdpr\Traits\IsPersonalDataSubject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A subject with a ULID key — the third key shape subject_id has to hold next
 * to a bigint (User) and a UUID (Applicant).
 */
class Visitor extends Model implements PersonalData
{
    use HasConsentRecords;
    use HasPersonalData;
    use HasUlids;
    use IsPersonalDataSubject;

    protected $table = 'visitors';

    protected $guarded = [];

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('name')->anonymizeWith('name')->exportable()
            ->field('email')->anonymizeWith('email')->exportable()
            ->retention(mode: RetentionMode::Delete)
            ->processOrder(1000);
    }
}
