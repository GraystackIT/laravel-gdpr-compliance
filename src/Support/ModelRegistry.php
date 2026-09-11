<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Contracts\PersonalData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Reads config('gdpr.models') and produces (cached) PersonalDataBlueprint
 * instances per model class.
 *
 * Supports two registration forms:
 *
 *   'models' => [
 *       \App\Models\User::class,              // simple: model self-describes
 *       \Vendor\Pkg\Thing::class => [         // vendor: external profile + scope
 *           'profile' => \App\Gdpr\Profiles\ThingProfile::class,
 *           'scope'   => \App\Gdpr\Scopes\ThingScope::class,
 *       ],
 *   ],
 */
class ModelRegistry
{
    /** @var array<class-string, PersonalDataBlueprint> */
    protected array $cache = [];

    /** @var array<class-string, class-string|null> */
    protected array $scopeCache = [];

    /**
     * @param  array<int|class-string, class-string|array<string, class-string>>  $config
     */
    public function __construct(protected array $config = []) {}

    /**
     * @return array<int, class-string>
     */
    public function all(): array
    {
        $classes = [];
        foreach ($this->config as $key => $value) {
            $classes[] = is_int($key) ? $value : $key;
        }

        return $classes;
    }

    public function has(string $modelClass): bool
    {
        return in_array($modelClass, $this->all(), true);
    }

    /**
     * Build (or return cached) blueprint for the given model class.
     */
    public function blueprintFor(string $modelClass): PersonalDataBlueprint
    {
        if (isset($this->cache[$modelClass])) {
            return $this->cache[$modelClass];
        }

        $profileClass = $this->profileClassFor($modelClass);

        if ($profileClass === null && ! is_subclass_of($modelClass, PersonalData::class)) {
            throw new InvalidArgumentException(sprintf(
                'Model %s is not registered with a profile and does not implement %s',
                $modelClass,
                PersonalData::class,
            ));
        }

        $profileSource = $profileClass !== null ? app($profileClass) : app($modelClass);

        if (! $profileSource instanceof PersonalData) {
            throw new InvalidArgumentException(sprintf(
                'Profile source %s does not implement %s',
                $profileSource::class,
                PersonalData::class,
            ));
        }

        $blueprint = $profileSource->personalData(new PersonalDataBlueprint)->build();

        return $this->cache[$modelClass] = $blueprint;
    }

    /**
     * Resolve the subject scope class (if any) registered in config for a model.
     *
     * @return class-string|null
     */
    public function scopeClassFor(string $modelClass): ?string
    {
        if (array_key_exists($modelClass, $this->scopeCache)) {
            return $this->scopeCache[$modelClass];
        }

        $entry = $this->config[$modelClass] ?? null;
        $scope = is_array($entry) ? ($entry['scope'] ?? null) : null;

        return $this->scopeCache[$modelClass] = $scope;
    }

    /**
     * Whether the given model class is a subject (i.e. uses IsPersonalDataSubject).
     */
    public function isSubject(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        return in_array(
            'GraystackIt\\Gdpr\\Traits\\IsPersonalDataSubject',
            class_uses_recursive($modelClass),
            true,
        );
    }

    /**
     * Return all registered subject classes.
     *
     * @return array<int, class-string>
     */
    public function subjects(): array
    {
        return array_values(array_filter($this->all(), fn (string $c) => $this->isSubject($c)));
    }

    /**
     * Clear the blueprint cache (useful for tests).
     */
    public function flush(): void
    {
        $this->cache = [];
        $this->scopeCache = [];
    }

    /**
     * @return class-string|null
     */
    protected function profileClassFor(string $modelClass): ?string
    {
        $entry = $this->config[$modelClass] ?? null;

        return is_array($entry) ? ($entry['profile'] ?? null) : null;
    }

    /**
     * Invoke scopePersonalDataForSubject on a query builder, using either the
     * model's own method or a registered scope class. Returns an unmodified
     * query if nothing is registered (caller should treat that as "skip").
     *
     * The subject's own model is the exception: it is scoped to the subject's
     * primary key, and its registered scope is read only for the global scopes
     * it removes. See applySubjectSelfScope().
     */
    public function applyScopeFor(
        string $modelClass,
        Builder $query,
        Model $subject,
    ): Builder {
        if ($modelClass === $subject::class) {
            return $this->applySubjectSelfScope($query, $subject);
        }

        $scopeClass = $this->scopeClassFor($modelClass);

        if ($scopeClass !== null) {
            return $scopeClass::apply($query, $subject);
        }

        if (method_exists($modelClass, 'scopePersonalDataForSubject')) {
            $query->personalDataForSubject($subject);

            return $query;
        }

        // No scope → return a never-matching query so the caller silently skips.
        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope a query to the rows of the subject's own model that belong to the
     * subject — always exactly one, the row carrying its primary key.
     *
     * The registered scope is consulted only for the global scopes it removes:
     * that is how a tenant-scoped model makes itself reachable outside a
     * request, and it is the only part of the scope that applies here. Its
     * filters describe how *other* subjects reach this model's rows, so a
     * subject model whose scope answers "1 = 0" for itself keeps working.
     */
    protected function applySubjectSelfScope(Builder $query, Model $subject): Builder
    {
        return $query
            ->withoutGlobalScopes($this->globalScopesRemovedBy($subject))
            ->whereKey($subject->getKey());
    }

    /**
     * The global scopes the subject's registered scope takes off a query, or
     * none when the subject registers no scope at all.
     *
     * @return array<int, string>
     */
    protected function globalScopesRemovedBy(Model $subject): array
    {
        $scopeClass = $this->scopeClassFor($subject::class);

        if ($scopeClass !== null) {
            return $scopeClass::apply($subject->newQuery(), $subject)->removedScopes();
        }

        if (method_exists($subject, 'scopePersonalDataForSubject')) {
            return $subject->newQuery()->personalDataForSubject($subject)->removedScopes();
        }

        return [];
    }
}
