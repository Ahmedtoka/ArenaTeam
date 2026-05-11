<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal comments on a task — distinct from the brand-group chat.
 *
 * Used for context that belongs ON the task itself (review notes, internal
 * discussion between AM and assignee) without polluting the brand chat.
 *
 * No soft delete per spec — task comments aren't audit-critical. Hard delete
 * via user-initiated action only; activity_feed captures the deletion event.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_task_comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('tos_tasks')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('body');

            $table->timestamps();

            // task_id auto-indexed by FK (covers "all comments on task X" — the only common query)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_task_comments');
    }
};
