<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task clarification cycles — one row per clarification request, with paired reply.
 *
 * Increments tos_tasks.revision_count on each insert. Replied rows
 * (replied_at IS NOT NULL) drive total_clarification_wait_seconds KPI.
 *
 * Cascade: task_id cascade (containment). Actor columns nullOnDelete.
 *
 * message_id and reply_message_id are nullable nullOnDelete per spec —
 * a hard-deleted chat message doesn't invalidate the revision record.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_task_revisions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('tos_tasks')
                ->cascadeOnDelete();

            $table->foreignId('requested_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('clarification_text');
            $table->foreignId('message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();
            $table->timestamp('requested_at');

            $table->timestamp('replied_at')->nullable();
            $table->text('reply_text')->nullable();
            $table->foreignId('reply_message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();

            $table->timestamps();

            // Open clarifications per task (KPI-relevant): WHERE task_id=? AND replied_at IS NULL
            $table->index(['task_id', 'replied_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_task_revisions');
    }
};
