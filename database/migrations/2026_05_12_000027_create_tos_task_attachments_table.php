<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to a task (reference docs, deliverables, briefs).
 *
 * Storage convention (set by App\TeamOS\Support\StoragePaths::forTask):
 *   teamos/tasks/{task_id}/{ulid}.{ext}
 *
 * disk default 'local' — flipped to 's3' via TEAMOS_DEFAULT_DISK env var
 * for new uploads (existing rows keep their original disk).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_task_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('tos_tasks')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();

            // task_id auto-indexed by FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_task_attachments');
    }
};
