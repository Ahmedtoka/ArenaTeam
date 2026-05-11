<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting RSVPs and attendance tracking.
 *
 * One row per (meeting, invited user).
 *
 * rsvp lifecycle:
 *   pending → accepted | declined | tentative
 *   (user can change their RSVP up to meeting start; rsvp_at captures the latest change)
 *
 * attended_at (added beyond the spec): timestamp of actual attendance, set when
 * the meeting starts and the user joins. Distinct from RSVP — an "accepted"
 * attendee may still no-show. Useful for the post-meeting attendance report
 * and KPI rollups ("which AMs consistently miss creative reviews?").
 *
 * Cascade behavior per spec: deleting a meeting or a user removes the RSVP row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_meeting_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meeting_id')
                ->constrained('tos_meetings')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('rsvp', ['pending', 'accepted', 'declined', 'tentative'])
                ->default('pending');
            $table->timestamp('rsvp_at')->nullable();

            // Actual attendance (set when the meeting goes in_progress and user joins)
            $table->timestamp('attended_at')->nullable();

            $table->timestamps();

            // One RSVP per (meeting, user)
            $table->unique(['meeting_id', 'user_id']);

            // "My upcoming accepted meetings" hot query:
            //   WHERE user_id=? AND rsvp='accepted' joined to meetings.starts_at > NOW()
            $table->index(['user_id', 'rsvp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_meeting_attendees');
    }
};
