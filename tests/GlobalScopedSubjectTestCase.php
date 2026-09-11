<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

use Workbench\App\Models\Member;
use Workbench\App\Models\MemberNote;
use Workbench\App\Models\UnreachableMember;

/**
 * Runs the package against subject models whose global scope hides every row
 * while no organization is bound — the state the queue and the scheduler run
 * in.
 */
abstract class GlobalScopedSubjectTestCase extends TestCase
{
    protected function registeredModels(): array
    {
        return [
            ...parent::registeredModels(),
            Member::class,
            MemberNote::class,
            UnreachableMember::class,
        ];
    }
}
