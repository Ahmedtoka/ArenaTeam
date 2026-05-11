<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled meetings — created from chat or directly from the calendar UI.
 *
 * Lifecycle:
 *   scheduled → in_progress → completed
 *              ↘ cancelled
 *
 * The host system message (a `meeting_card` row in tos_messages) is linked via
 * message_id. nullOnDelete preserves the meeting if the original card message
 * is hard-deleted (rare; soft-delete is the norm).
 *
 * Cascade behavior:
 *   - brand_group_id: cascade (deleting the group removes its meetings)
 *   - created_by_id: nullOnDelete (preserve meeting history if creator hard-deletes)
 *   - cancelled_by_id: nullOnDelete (same reason)
 *   - message_id: nullOnDelete
 *
 * Location vs link:
 *   - location: physical address ("Studio X, 5th floor"); nullable
 *   - online_meeting_link: URL for online attendance; nullable
 *   - Hybrid meetings may have BOTH populated.
 *
 * Recurrence: NOT supported in v1. All meetings are one-off.
 *
 * Reminders:
 *   - reminder_sent_at protects against duplicate "starting in 15 min" notifications
 *     when the scheduler runs twice (Windows scheduler quirks). The job sets this
 *     when it dispatches; subsequent runs skip rows where reminder_sent_at IS NOT NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_meetings', function (Blueprint $table) {
            $table->id();

            // Containment + audit actors
            $table->foreignId('brand_group_id')
                ->constrained('tos_brand_groups')
                ->cascadeOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Host chat message (the meeting_card system row)
            $table->foreignId('message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();

            // Content
            $table->string('title', 255);
            $table->text('agenda')->nullable();

            // Schedule
            // Using dateTime() (DATETIME) instead of timestamp() (TIMESTAMP) for two reasons:
            //  1. MariaDB strict mode rejects '0000-00-00' implicit default on the SECOND
            //     required TIMESTAMP in a table (explicit_defaults_for_timestamp behavior).
            //     This is the only table in the schema with two required side-by-side
            //     datetime columns, so it's the only place this matters.
            //  2. DATETIME stores literal values (no UTC auto-conversion) which is more
            //     intuitive for "scheduled at 14:00 local" semantics. App layer still
            //     normalizes to UTC at write time per §18 of the prompt.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // Location (mutually-augmentable for hybrid)
            $table->string('location', 255)->nullable();
            $table->string('online_meeting_link', 500)->nullable();
            $table->enum('mode', ['in_person', 'online', 'hybrid'])->default('online');

            // Pre-meeting references (attachment IDs resolved against tos_message_attachments at render time)
            $table->json('reference_files')->nullable();

            // Lifecycle
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])
                ->default('scheduled');

            // Cancellation audit
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();

            // Post-meeting notes (markdown)
            $table->text('minutes')->nullable();

            // Idempotency for "starting in 15 min" reminders
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();

            // Indexes
            // Calendar view: "meetings this week" — WHERE starts_at BETWEEN ? AND ?
            $table->index('starts_at');

            // Brand timeline: "all Aliya meetings" — WHERE brand_group_id = ? ORDER BY starts_at
            $table->index(['brand_group_id', 'starts_at']);

            // Reminder & soon-starting queries:
            //   WHERE status='scheduled' AND starts_at BETWEEN NOW() AND NOW()+15min AND reminder_sent_at IS NULL
            // Composite (status, starts_at) handles the status filter + time range;
            // reminder_sent_at IS NULL is a residual filter (cheap, the candidate set is tiny).
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_meetings');
    }
};
