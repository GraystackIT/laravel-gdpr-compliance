<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->index(['subject_type', 'subject_id']);

            $table->string('purpose');
            $table->index('purpose');

            // 'grant' | 'withdraw'
            $table->string('action');

            // 'cookie_banner' | 'profile_settings' | 'api' | etc. — free-form
            $table->string('source')->nullable();

            // free-form context (policy_version, truncated_ip, …). NEVER raw PII.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'purpose', 'created_at'], 'consents_latest_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
