<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_audits', function (Blueprint $table) {
            $table->id();

            // Subject FK (orphaned after hard delete is intentional)
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->index(['subject_type', 'subject_id']);

            $table->string('event');
            $table->index('event');

            // Per-model events: which model class was affected
            $table->string('target_model')->nullable();

            // Number of rows touched (when applicable)
            $table->unsignedInteger('affected_rows')->nullable();

            // Free-form context: snapshots, field names, legal_basis, hold_until.
            // NEVER contains PII values.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_audits');
    }
};
