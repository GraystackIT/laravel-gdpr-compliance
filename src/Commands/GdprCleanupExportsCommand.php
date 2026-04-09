<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Commands;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GdprCleanupExportsCommand extends Command
{
    protected $signature = 'gdpr:cleanup-exports {--disk=local}';

    protected $description = 'Delete expired export files and null out their storage paths on the requests.';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');

        $expired = GdprRequest::query()
            ->whereNotNull('export_file_path')
            ->whereNotNull('export_expires_at')
            ->where('export_expires_at', '<', now())
            ->get();

        $removed = 0;
        foreach ($expired as $request) {
            if (Storage::disk($disk)->exists($request->export_file_path)) {
                Storage::disk($disk)->delete($request->export_file_path);
                $removed++;
            }
            $request->export_file_path = null;
            $request->save();
        }

        $this->info("Removed {$removed} expired export file(s).");

        return self::SUCCESS;
    }
}
