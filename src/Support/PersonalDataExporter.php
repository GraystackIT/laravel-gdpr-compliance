<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Collects a subject's exportable data across all registered models
 * and writes a single JSON file. Returns the storage path.
 */
class PersonalDataExporter
{
    public function __construct(
        protected ModelRegistry $registry,
        protected SubjectRecordResolver $resolver,
    ) {}

    /**
     * @return array{path: string, manifest: array<string, mixed>}
     */
    public function export(Model $subject, GdprRequest $request, string $disk = 'local', string $pathPrefix = 'gdpr/exports'): array
    {
        $data = [];

        foreach ($this->resolver->queriesFor($subject) as $entry) {
            $modelClass = $entry['model_class'];
            $rows = $entry['query']->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $blueprint = $this->registry->blueprintFor($modelClass);
            $exportableFields = array_map(fn ($f) => $f->name, $blueprint->exportableFields());

            if ($exportableFields === []) {
                continue;
            }

            $data[$modelClass] = $rows->map(
                fn (Model $row) => collect($row->getAttributes())
                    ->only(array_merge([$row->getKeyName()], $exportableFields))
                    ->toArray()
            )->values()->toArray();
        }

        $payload = [
            'subject' => [
                'type' => $subject::class,
                'id' => $subject->getKey(),
                'exported_at' => now()->toIso8601String(),
            ],
            'data' => $data,
            'manifest' => [
                'generated_at' => now()->toIso8601String(),
                'request_id' => $request->id,
                'package_version' => '0.1.0',
            ],
        ];

        $filename = sprintf(
            '%s/%s-%s.json',
            $pathPrefix,
            Str::slug(class_basename($subject)),
            (string) Str::ulid(),
        );

        Storage::disk($disk)->put($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return ['path' => $filename, 'manifest' => $payload['manifest']];
    }
}
