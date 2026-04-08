<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\RetentionMode;
use LogicException;

/**
 * Fluent builder and immutable value object for a model's personal data profile.
 *
 * A blueprint is mutated via the chained builder methods (->field(), ->retention(),
 * ->processOrder()) and then frozen via build(). After build() is called, further
 * mutation throws. The resolver typically calls build() implicitly when extracting
 * field definitions, retention, or the process order.
 */
class PersonalDataBlueprint
{
    public const GRACE_PERIOD_HARD_CAP_DAYS = 30;

    public const DEFAULT_PROCESS_ORDER = 100;

    /** @var array<string, array{anonymizer_alias: ?string, anonymizer_config: array<string, mixed>, exportable: bool}> */
    protected array $fields = [];

    protected ?string $currentField = null;

    protected ?RetentionPolicy $retention = null;

    protected int $processOrder = self::DEFAULT_PROCESS_ORDER;

    protected bool $frozen = false;

    public function field(string $name): self
    {
        $this->assertMutable();

        if (! isset($this->fields[$name])) {
            $this->fields[$name] = [
                'anonymizer_alias' => null,
                'anonymizer_config' => [],
                'exportable' => false,
            ];
        }

        $this->currentField = $name;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function anonymizeWith(string $alias, array $config = []): self
    {
        $this->assertMutable();
        $this->assertCurrentField('anonymizeWith');

        $this->fields[$this->currentField]['anonymizer_alias'] = $alias;
        $this->fields[$this->currentField]['anonymizer_config'] = $config;

        return $this;
    }

    public function exportable(): self
    {
        $this->assertMutable();
        $this->assertCurrentField('exportable');

        $this->fields[$this->currentField]['exportable'] = true;

        return $this;
    }

    public function retention(
        RetentionMode $mode = RetentionMode::Delete,
        int $gracePeriodDays = 0,
        ?int $legalHoldDays = null,
        ?string $legalBasis = null,
    ): self {
        $this->assertMutable();

        if ($gracePeriodDays < 0) {
            throw new InvalidRetentionConfig('gracePeriodDays must be >= 0');
        }

        if ($gracePeriodDays > self::GRACE_PERIOD_HARD_CAP_DAYS) {
            throw new InvalidRetentionConfig(sprintf(
                'gracePeriodDays may not exceed %d (DSGVO Art. 12(3) "within one month"); got %d',
                self::GRACE_PERIOD_HARD_CAP_DAYS,
                $gracePeriodDays,
            ));
        }

        if ($mode === RetentionMode::LegalHold && ($legalHoldDays === null || $legalHoldDays <= 0)) {
            throw new InvalidRetentionConfig('legalHoldDays must be > 0 when mode is legal_hold');
        }

        $this->retention = new RetentionPolicy(
            mode: $mode,
            gracePeriodDays: $gracePeriodDays,
            legalHoldDays: $legalHoldDays,
            legalBasis: $legalBasis,
        );

        return $this;
    }

    public function processOrder(int $order): self
    {
        $this->assertMutable();

        if ($order < 0 || $order > 65535) {
            throw new InvalidRetentionConfig('processOrder must be between 0 and 65535');
        }

        $this->processOrder = $order;

        return $this;
    }

    /**
     * Freeze the builder into an immutable blueprint. Validates that every
     * declared field is functional (anonymizable, exportable, or both).
     */
    public function build(): self
    {
        if ($this->frozen) {
            return $this;
        }

        foreach ($this->fields as $name => $def) {
            if ($def['anonymizer_alias'] === null && $def['exportable'] === false) {
                throw new InvalidRetentionConfig(sprintf(
                    'field "%s" has neither anonymizeWith() nor exportable() — remove it or configure one of them',
                    $name,
                ));
            }
        }

        if ($this->retention === null) {
            $this->retention = new RetentionPolicy(
                mode: RetentionMode::Delete,
                gracePeriodDays: 0,
                legalHoldDays: null,
                legalBasis: null,
            );
        }

        $this->frozen = true;
        $this->currentField = null;

        return $this;
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        $this->build();

        return array_map(
            fn (string $name, array $def): FieldDefinition => new FieldDefinition(
                name: $name,
                anonymizerAlias: $def['anonymizer_alias'],
                anonymizerConfig: $def['anonymizer_config'],
                exportable: $def['exportable'],
            ),
            array_keys($this->fields),
            array_values($this->fields),
        );
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function anonymizableFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            fn (FieldDefinition $f) => $f->isAnonymizable(),
        ));
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function exportableFields(): array
    {
        return array_values(array_filter(
            $this->fields(),
            fn (FieldDefinition $f) => $f->isExportable(),
        ));
    }

    public function retentionPolicy(): RetentionPolicy
    {
        $this->build();

        return $this->retention;
    }

    public function getProcessOrder(): int
    {
        $this->build();

        return $this->processOrder;
    }

    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    protected function assertMutable(): void
    {
        if ($this->frozen) {
            throw new LogicException('PersonalDataBlueprint is frozen; cannot mutate after build().');
        }
    }

    protected function assertCurrentField(string $calledMethod): void
    {
        if ($this->currentField === null) {
            throw new LogicException(sprintf(
                '%s() must be called after field(); no current field',
                $calledMethod,
            ));
        }
    }
}
