<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Models\GdprAudit;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit, event-driven audit writer for the deletion/export pipeline.
 *
 * Never inspects model attributes, never logs values, never uses Eloquent
 * observers. Services call log() explicitly with the exact event they want
 * to record. The AuditLogger enforces the "no PII" invariant.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $event,
        ?Model $subject = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $targetModel = null,
        ?int $affectedRows = null,
        array $context = [],
    ): GdprAudit {
        [$type, $id] = $this->resolveSubject($subject, $subjectType, $subjectId);

        return GdprAudit::create([
            'subject_type' => $type,
            'subject_id' => $id,
            'event' => $event,
            'target_model' => $targetModel,
            'affected_rows' => $affectedRows,
            'context' => $this->sanitizeContext($context),
        ]);
    }

    /**
     * @return array{0: string, 1: int}
     */
    protected function resolveSubject(?Model $subject, ?string $subjectType, ?int $subjectId): array
    {
        if ($subject !== null) {
            return [$subject::class, (int) $subject->getKey()];
        }

        if ($subjectType === null || $subjectType === '' || $subjectId === null) {
            throw new \InvalidArgumentException(
                'AuditLogger::log() requires either a $subject model or both $subjectType and $subjectId.',
            );
        }

        return [$subjectType, $subjectId];
    }

    /**
     * Sanitize the context array: strip any keys that look like raw PII.
     * Callers are trusted to not pass values directly, but this is a
     * defense-in-depth layer.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function sanitizeContext(array $context): array
    {
        $blacklist = ['email', 'name', 'phone', 'ip', 'ip_address', 'user_agent', 'password'];

        foreach ($blacklist as $key) {
            if (array_key_exists($key, $context)) {
                unset($context[$key]);
            }
        }

        return $context;
    }
}
