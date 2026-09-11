<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

use Workbench\App\Models\Applicant;
use Workbench\App\Models\ApplicantNote;

/**
 * Runs the package with subject_key_type = 'string', the mode that holds
 * numeric (User) and UUID (Applicant) subject keys in the same tables.
 */
abstract class StringSubjectKeyTestCase extends TestCase
{
    protected string $subjectKeyType = 'string';

    protected function registeredModels(): array
    {
        return [
            ...parent::registeredModels(),
            Applicant::class,
            ApplicantNote::class,
        ];
    }
}
