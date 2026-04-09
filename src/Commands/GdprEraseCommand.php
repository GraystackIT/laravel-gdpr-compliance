<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class GdprEraseCommand extends Command
{
    protected $signature = 'gdpr:erase
        {subject : Subject class name (FQCN)}
        {id : Subject primary key}
        {--now : Skip grace period and process immediately}';

    protected $description = 'Request deletion of a subject, optionally bypassing the grace period.';

    public function handle(GdprManager $manager): int
    {
        $class = (string) $this->argument('subject');
        $id = (string) $this->argument('id');

        if (! class_exists($class)) {
            $this->error("Class {$class} does not exist.");

            return self::FAILURE;
        }

        /** @var Model|null $subject */
        $subject = $class::query()->find($id);
        if ($subject === null) {
            $this->error("Subject {$class}#{$id} not found.");

            return self::FAILURE;
        }

        $request = $this->option('now')
            ? $manager->deleteImmediately($subject)
            : $manager->requestDeletion($subject);

        $this->info(sprintf('Deletion request #%d created with status %s.', $request->id, $request->status->value));

        return self::SUCCESS;
    }
}
