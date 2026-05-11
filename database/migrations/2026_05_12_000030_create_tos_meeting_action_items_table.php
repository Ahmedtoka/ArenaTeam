<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action items captured from meeting minutes.
 *
 * Optionally linked to a full task via task_id (when the action item is
 * promoted to a tracked task). Many action items stay lightweight — just a
 * description, assigned user, and due date — without ever becoming a task.
 *
 * Cascade: meeting_id cascade (containment). task_id nullOnDelete per spec —
 * unlinking the task leaves the action item intact for the meeting record.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_meeting_action_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meeting_id')
                ->constrained('tos_meetings')
                ->cascadeOnDelete();
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tos_tasks')
                ->nullOnDelete();

            $table->string('description');
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('due_at')->nullable();

            $table->timestamps();

            // "Open action items for user X" — assigned_to_id auto-indexed by FK.
            // "Action items for meeting X" — meeting_id auto-indexed by FK.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_meeting_action_items');
    }
};
