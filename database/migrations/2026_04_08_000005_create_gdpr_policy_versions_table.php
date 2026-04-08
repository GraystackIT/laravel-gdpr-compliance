<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('slug');          // e.g. 'privacy', 'imprint', 'tos'
            $table->string('version');       // e.g. '2026-04'
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('url')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['slug', 'version']);
            $table->index('slug');
        });

        Schema::create('gdpr_policy_acceptances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gdpr_policy_version_id')
                ->constrained('gdpr_policy_versions')
                ->cascadeOnDelete();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->index(['subject_type', 'subject_id']);

            // free-form context (source, truncated_ip). NEVER raw PII.
            $table->json('context')->nullable();

            $table->timestamp('accepted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_policy_acceptances');
        Schema::dropIfExists('gdpr_policy_versions');
    }
};
