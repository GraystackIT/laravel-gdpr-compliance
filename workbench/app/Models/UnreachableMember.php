<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use GraystackIt\Gdpr\Traits\HasPersonalData;
use GraystackIt\Gdpr\Traits\IsPersonalDataSubject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The misconfiguration: a tenant-scoped subject that never tells the package
 * how to reach itself — a global scope that hides every row without a bound
 * organization and no scopePersonalDataForSubject() to remove it. Shares the
 * members table with {@see Member}.
 */
class UnreachableMember extends Model implements PersonalData
{
    use HasPersonalData;
    use IsPersonalDataSubject;

    protected $table = 'members';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope(Member::OrganizationScope, function (Builder $query): void {
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
            ->retention(mode: RetentionMode::Delete)
            ->processOrder(1000);
    }
}
