<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity feed — immutable audit log for cross-cutting events.
 *
 * Captures domain events that span multiple tables and benefit from a single
 * queryable timeline. Examples of `action` values:
 *   task.created, task.assigned, task.completed, task.approved,
 *   message.marked_red, message.marked_orange, message.marked_green,
 *   group.member_added, group.member_removed,
 *   meeting.scheduled, meeting.cancelled,
 *   brand.archived, shoot.delivered, ...
 *
 * payload JSON: event-specific data (target IDs, before/after state, etc.).
 *
 * Cascade behavior: both FKs are nullable + nullOnDelete to preserve the
 * audit record even when the actor or brand context is later removed. This
 * is intentionally history-preserving — the feed survives subject deletion.
 *
 * Append-only: no soft-delete, no updated_at meaning (timestamps() kept for
 * Laravel convention; updated_at == created_at in practice).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_activity_feed', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->string('action');
            $table->json('payload');

            $table->timestamps();

            // Brand-scoped timeline ("recent activity on Aliya")
            $table->index(['brand_id', 'created_at']);
            // User-scoped timeline ("what has Fairouz been doing this week")
            $table->index(['user_id', 'created_at']);
            // Action-typed queries ("all task.completed events in the last 24h")
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_activity_feed');
    }
};
