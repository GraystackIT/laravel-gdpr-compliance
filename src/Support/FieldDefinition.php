<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

final readonly class FieldDefinition
{
    /**
     * @param  array<string, mixed>  $anonymizerConfig
     */
    public function __construct(
        public string $name,
        public ?string $anonymizerAlias,
        public array $anonymizerConfig,
        public bool $exportable,
    ) {}

    public function isAnonymizable(): bool
    {
        return $this->anonymizerAlias !== null;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }
}
