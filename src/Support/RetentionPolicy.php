<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\RetentionMode;

final readonly class RetentionPolicy
{
    public function __construct(
        public RetentionMode $mode,
        public int $gracePeriodDays,
        public ?int $legalHoldDays,
        public ?string $legalBasis,
    ) {}

    public function hasGrace(): bool
    {
        return $this->gracePeriodDays > 0;
    }

    public function requiresLegalHold(): bool
    {
        return $this->mode === RetentionMode::LegalHold;
    }

    /**
     * Serialize this policy for snapshotting into gdpr_deletions.retention_snapshot.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'mode' => $this->mode->value,
            'grace_period_days' => $this->gracePeriodDays,
            'legal_hold_days' => $this->legalHoldDays,
            'legal_basis' => $this->legalBasis,
        ];
    }

    /**
     * Rehydrate a policy from a snapshot (e.g. loaded from the database).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            mode: RetentionMode::from($snapshot['mode']),
            gracePeriodDays: (int) $snapshot['grace_period_days'],
            legalHoldDays: isset($snapshot['legal_hold_days']) ? (int) $snapshot['legal_hold_days'] : null,
            legalBasis: $snapshot['legal_basis'] ?? null,
        );
    }
}
