<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-scoped related model of {@see Member}, carrying the same global
 * scope and removing it in its subject scope.
 */
class MemberNote extends Model implements PersonalData
{
    protected $table = 'member_notes';

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
            ->field('body')->anonymizeWith('free_text')->exportable()
            ->retention(mode: RetentionMode::Anonymize)
            ->processOrder(100);
    }

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof Member => $query
                ->withoutGlobalScope(Member::OrganizationScope)
                ->where('member_id', $subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
