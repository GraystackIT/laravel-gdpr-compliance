<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Tests\StringSubjectKeyTestCase;
use GraystackIt\Gdpr\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(StringSubjectKeyTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('SubjectKeyTypes');

pest()->extend(TestCase::class)->in('Unit');
