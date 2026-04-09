<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Jobs;

use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Events\PersonalDataExported;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

class PreparePersonalDataExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $requestId) {}

    public function handle(PersonalDataExporter $exporter): void
    {
        /** @var GdprRequest|null $request */
        $request = GdprRequest::find($this->requestId);

        if ($request === null || $request->status->isTerminal()) {
            return;
        }

        $subjectClass = $request->subject_type;
        if (! class_exists($subjectClass)) {
            $request->status = RequestStatus::Failed;
            $request->save();

            return;
        }

        $subject = $subjectClass::query()->find($request->subject_id);
        if ($subject === null) {
            $request->status = RequestStatus::Failed;
            $request->save();

            return;
        }

        try {
            $result = $exporter->export($subject, $request);
        } catch (\Throwable $e) {
            $request->status = RequestStatus::Failed;
            $request->completed_at = now();
            $request->save();

            throw $e;
        }

        $request->status = RequestStatus::Completed;
        $request->export_file_path = $result['path'];
        $request->export_expires_at = now()->addDays(7);
        $request->completed_at = now();
        $request->save();

        Event::dispatch(new PersonalDataExported($request));
    }
}
