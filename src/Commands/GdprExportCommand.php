<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Jobs\PreparePersonalDataExportJob;
use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class GdprExportCommand extends Command
{
    protected $signature = 'gdpr:export {subject : Subject class name (FQCN)} {id : Subject primary key}';

    protected $description = 'Request a personal data export for a subject and dispatch the export job.';

    public function handle(GdprManager $manager): int
    {
        $class = (string) $this->argument('subject');
        $id = (int) $this->argument('id');

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

        $request = $manager->requestExport($subject);
        PreparePersonalDataExportJob::dispatch($request->id);

        $this->info(sprintf('Export request #%d created and dispatched.', $request->id));

        return self::SUCCESS;
    }
}
