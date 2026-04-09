<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Models\GdprAudit;
use Illuminate\Console\Command;

class GdprAuditCommand extends Command
{
    protected $signature = 'gdpr:audit
        {--subject= : Filter by subject type (FQCN)}
        {--id= : Filter by subject id}
        {--event= : Filter by event name}
        {--limit=20 : Max rows to show}';

    protected $description = 'Show recent GDPR audit entries.';

    public function handle(): int
    {
        $query = GdprAudit::query()->latest('created_at');

        if ($subject = $this->option('subject')) {
            $query->where('subject_type', $subject);
        }
        if ($id = $this->option('id')) {
            $query->where('subject_id', $id);
        }
        if ($event = $this->option('event')) {
            $query->where('event', $event);
        }

        $rows = $query->limit((int) $this->option('limit'))->get();

        if ($rows->isEmpty()) {
            $this->info('No audit rows found.');

            return self::SUCCESS;
        }

        $this->table(
            ['When', 'Subject', 'Event', 'Target', 'Rows'],
            $rows->map(fn ($r) => [
                $r->created_at?->format('Y-m-d H:i:s'),
                class_basename($r->subject_type).'#'.$r->subject_id,
                $r->event,
                $r->target_model ? class_basename($r->target_model) : '—',
                $r->affected_rows ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
