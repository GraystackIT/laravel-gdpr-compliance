<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use GraystackIt\Gdpr\Traits\HasConsentRecords;
use GraystackIt\Gdpr\Traits\HasPersonalData;
use GraystackIt\Gdpr\Traits\IsPersonalDataSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped subject. The global scope restricts every query to the bound
 * organization and matches nothing at all while none is bound — the state a
 * queue worker and the scheduler run in. This is what a multi-tenancy package
 * does to an application's subject model.
 */
class Member extends Model implements PersonalData
{
    use HasConsentRecords;
    use HasPersonalData;
    use IsPersonalDataSubject;

    public const OrganizationScope = 'organization';

    protected $table = 'members';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope(self::OrganizationScope, function (Builder $query): void {
            $organizationId = config('workbench.organization_id');

            if ($organizationId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where($query->getModel()->getTable().'.organization_id', $organizationId);
        });
    }

    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('name')->anonymizeWith('name')->exportable()
            ->field('email')->anonymizeWith('email')->exportable()
            ->retention(mode: RetentionMode::Delete, gracePeriodDays: 7)
            ->processOrder(1000);
    }

    /**
     * Reaching the subject's own row must not depend on a bound organization.
     */
    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof self => $query
                ->withoutGlobalScope(self::OrganizationScope)
                ->whereKey($subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
