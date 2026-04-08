<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->index(['subject_type', 'subject_id']);

            $table->string('type');   // 'delete' | 'anonymize' | 'export'
            $table->string('status'); // 'pending' | 'processing' | 'completed' | 'cancelled' | 'failed'
            $table->index('status');

            // Email snapshot at request time — used for notifications after
            // subject is anonymized/deleted. Wiped on final notification
            // send (layer 1) or 7 days after terminal status (layer 2).
            $table->string('notification_email')->nullable();

            // Export-specific fields
            $table->string('export_file_path')->nullable();
            $table->timestamp('export_expires_at')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
