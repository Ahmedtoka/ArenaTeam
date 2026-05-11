<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand group memberships — who is in which chat room.
 *
 * Soft-delete model:
 *  - removed_at IS NULL    → currently an active member
 *  - removed_at IS NOT NULL → soft-removed (the row is retained for attribution
 *    in historical messages and activity feeds).
 *
 * IMPORTANT DESIGN NOTE on multi-row vs single-row history:
 *  The original plan was multiple historical rows per (group, user) pair, each
 *  representing a join→leave episode, distinguished by removed_at. While
 *  writing this migration I caught a MariaDB semantic gotcha: ANSI SQL UNIQUE
 *  constraints DO NOT treat two NULL values as equal. So a UNIQUE on
 *  (brand_group_id, user_id, removed_at) would permit multiple "active" rows
 *  (removed_at = NULL) for the same user in the same group — a real bug, not
 *  a feature. Workarounds (virtual-column hash, sentinel timestamp like
 *  '9999-12-31', partial unique indexes) all add complexity for a marginal
 *  benefit — the agency-side history queries can be answered identically by
 *  joining tos_activity_feed entries ('group.member_added' / '.member_removed').
 *
 *  Final design: ONE row per (group, user). On rejoin, the existing row is
 *  UPDATEd back to active (removed_at = NULL, added_by_id refreshed, etc.) —
 *  see BrandGroupMembership service. Episode-level history lives in activity_feed.
 *  If we ever need true multi-row episodic history at the DB layer, migrate to
 *  a generated-column unique key in a follow-up.
 *
 * Notification overlap with tos_user_group_subscriptions:
 *  - `muted_until` here = temporary snooze ("mute for 8 hours"); auto-expires.
 *  - subscriptions.mode = permanent preference ('all' / 'mentions_only' / 'muted').
 *  Both are checked at notification dispatch — see SendNotification middleware.
 *
 * Deferred field: `last_read_message_id` FK → tos_messages.id will be added in
 * Group 3 (forward-FK avoided — tos_messages doesn't exist yet). For now,
 * unread counts use last_read_at vs message.created_at — works for our scale,
 * upgrades to a row-ID pointer once messages exists.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_brand_group_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_group_id')
                ->constrained('tos_brand_groups')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Who added this user to the group (NULL when self-joined, e.g., via an open-visibility group in future)
            $table->foreignId('added_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Per-group role (group-level admin can manage members; member can chat only)
            $table->enum('role_in_group', ['admin', 'member'])->default('member');

            // Read state — NULL on insert, set on first chat-open
            $table->timestamp('last_read_at')->nullable();

            // Temporary snooze (auto-expires); distinct from subscriptions.mode='muted' permanent
            $table->timestamp('muted_until')->nullable();

            // Personal pin to top of chat list (per-user, NOT global)
            $table->timestamp('pinned_at')->nullable();

            // Soft-delete
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // One row per (group, user) — see design note above re: rejoin semantics.
            $table->unique(['brand_group_id', 'user_id']);

            // user_id alone is auto-indexed by its FK constraint (MariaDB requirement),
            // and the unique above covers user_id-only queries via the wrong-position prefix
            // (brand_group_id is leftmost) — so we DO need the FK auto-index for "all
            // groups for user X" queries. No additional standalone index needed.

            // Unread-count + recent-activity-per-user queries:
            //   WHERE user_id = ? AND last_read_at < X
            $table->index(['user_id', 'last_read_at']);

            // Active-membership filter scans
            $table->index('removed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_brand_group_members');
    }
};
