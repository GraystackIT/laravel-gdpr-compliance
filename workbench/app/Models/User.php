<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use GraystackIt\Gdpr\Traits\HasConsentRecords;
use GraystackIt\Gdpr\Traits\HasPersonalData;
use GraystackIt\Gdpr\Traits\IsPersonalDataSubject;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model implements AuthenticatableContract, PersonalData
{
    use Authenticatable;
    use HasConsentRecords;
    use HasPersonalData;
    use IsPersonalDataSubject;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('name')->anonymizeWith('name')->exportable()
            ->field('email')->anonymizeWith('email')->exportable()
            ->field('phone')->anonymizeWith('phone')->exportable()
            ->field('password')->anonymizeWith('static_text', ['value' => '[ANONYMIZED]'])
            ->field('created_at')->exportable()
            ->retention(mode: RetentionMode::Delete, gracePeriodDays: 7)
            ->processOrder(1000);
    }
}
