<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies the anonymizers defined in a model's profile to a set of rows.
 *
 * Uses saveQuietly() to avoid triggering Eloquent events (observers,
 * model events) which could otherwise create feedback loops.
 */
class PersonalDataEraser
{
    public function __construct(
        protected ModelRegistry $registry,
        protected AnonymizerManager $anonymizers,
    ) {}

    /**
     * Wipe all anonymizable fields on the given rows according to their
     * model's profile. Returns the count of rows processed.
     */
    public function eraseRows(string $modelClass, Collection $rows): int
    {
        $blueprint = $this->registry->blueprintFor($modelClass);
        $fields = $blueprint->anonymizableFields();

        if ($fields === []) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            /** @var Model $row */
            foreach ($fields as $field) {
                $row->setAttribute(
                    $field->name,
                    $this->anonymizers->anonymize(
                        $field->anonymizerAlias,
                        $row->getAttribute($field->name),
                        $field->anonymizerConfig,
                    ),
                );
            }
            $row->saveQuietly();
            $count++;
        }

        return $count;
    }

    /**
     * Hard-delete the given rows without anonymization first.
     */
    public function deleteRows(Collection $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            /** @var Model $row */
            $row->delete();
            $count++;
        }

        return $count;
    }
}
