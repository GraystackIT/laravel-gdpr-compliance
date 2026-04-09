<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * For a given subject, iterates the registered models and yields
 * the rows of each that belong to the subject, via the registered
 * scope (either scopePersonalDataForSubject on the model or a config
 * scope class).
 */
class SubjectRecordResolver
{
    public function __construct(protected ModelRegistry $registry) {}

    /**
     * @return iterable<array{model_class: class-string, query: Builder}>
     */
    public function queriesFor(Model $subject): iterable
    {
        foreach ($this->registry->all() as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            /** @var Model $instance */
            $instance = new $modelClass;
            $query = $instance->newQuery();

            // If this model IS the subject, scope to itself by primary key.
            if ($modelClass === $subject::class) {
                $query->whereKey($subject->getKey());
            } else {
                $query = $this->registry->applyScopeFor($modelClass, $query, $subject);
            }

            yield ['model_class' => $modelClass, 'query' => $query];
        }
    }

    /**
     * Count rows per registered model for the given subject.
     *
     * @return array<class-string, int>
     */
    public function countsFor(Model $subject): array
    {
        $counts = [];
        foreach ($this->queriesFor($subject) as $entry) {
            $counts[$entry['model_class']] = (int) $entry['query']->count();
        }

        return $counts;
    }

    /**
     * Fetch rows per registered model for the given subject.
     *
     * @return array<class-string, Collection>
     */
    public function rowsFor(Model $subject): array
    {
        $rows = [];
        foreach ($this->queriesFor($subject) as $entry) {
            $rows[$entry['model_class']] = $entry['query']->get();
        }

        return $rows;
    }
}
