<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user unified calendar — meetings, task deadlines, shoots, check-ins, manual.
 *
 * source_type + source_id is polymorphic-lite (NO FK constraint on source_id),
 * because source_type values map to different tables (tos_meetings, tos_tasks,
 * tos_shoots, tos_attendance_sessions). The CalendarEvent model uses Eloquent
 * morph helpers to resolve back to the source.
 *
 * Fan-out: when a meeting is created with 5 attendees, 5 calendar_events rows
 * are inserted (one per attendee). Same for shoot collaborators, etc.
 *
 * Cascade: user_id cascade — calendar belongs to the user; if user is hard-deleted,
 * their calendar goes too.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_calendar_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('source_type', ['meeting', 'task_deadline', 'shoot', 'checkin', 'manual'])
                ->default('manual');
            $table->unsignedBigInteger('source_id')->nullable(); // polymorphic; no FK constraint

            $table->string('title');
            $table->text('details')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('color')->nullable();

            $table->timestamps();

            // Per-user date-range query (calendar view): WHERE user_id=? AND starts_at BETWEEN ?
            $table->index(['user_id', 'starts_at']);

            // Source backlink lookup: "find calendar event for this meeting" — used when a meeting
            // is rescheduled/cancelled and we need to update or delete the event row.
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_calendar_events');
    }
};
