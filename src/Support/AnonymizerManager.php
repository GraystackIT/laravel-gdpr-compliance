<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Contracts\Anonymizer;
use InvalidArgumentException;

/**
 * Resolves anonymizer aliases to concrete Anonymizer instances and caches them.
 */
class AnonymizerManager
{
    /** @var array<string, Anonymizer> */
    protected array $cache = [];

    /**
     * @param  array<string, class-string<Anonymizer>>  $aliases
     */
    public function __construct(protected array $aliases = []) {}

    public function register(string $alias, string $fqcn): void
    {
        $this->aliases[$alias] = $fqcn;
        unset($this->cache[$alias]);
    }

    public function has(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    public function resolve(string $alias): Anonymizer
    {
        if (isset($this->cache[$alias])) {
            return $this->cache[$alias];
        }

        if (! isset($this->aliases[$alias])) {
            throw new InvalidArgumentException("Unknown anonymizer alias: {$alias}");
        }

        $fqcn = $this->aliases[$alias];
        $instance = app($fqcn);

        if (! $instance instanceof Anonymizer) {
            throw new InvalidArgumentException(sprintf(
                'Anonymizer %s does not implement %s',
                $fqcn,
                Anonymizer::class,
            ));
        }

        return $this->cache[$alias] = $instance;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function anonymize(string $alias, mixed $value, array $config = []): mixed
    {
        return $this->resolve($alias)->anonymize($value, $config);
    }

    /**
     * @return array<string, class-string<Anonymizer>>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }
}
