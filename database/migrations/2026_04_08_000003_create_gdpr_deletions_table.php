<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_deletions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gdpr_request_id')
                ->constrained('gdpr_requests')
                ->cascadeOnDelete();

            // Subject (who requested)
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->index(['subject_type', 'subject_id']);

            // Affected model (one row per affected registered model; may
            // equal subject_type when the row represents the subject itself)
            $table->string('target_model');
            $table->index('target_model');

            // Snapshot of the retention config at request time (snapshot wins).
            $table->json('retention_snapshot');

            // State machine
            $table->string('state'); // pending_grace | pending_legal_hold | anonymized | erased | cancelled
            $table->index('state');

            // Processing order (snapshot from profile; lower = earlier)
            $table->unsignedSmallInteger('process_order')->default(100);
            $table->index(['gdpr_request_id', 'process_order']);

            $table->timestamp('scheduled_for');
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_deletions');
    }
};
