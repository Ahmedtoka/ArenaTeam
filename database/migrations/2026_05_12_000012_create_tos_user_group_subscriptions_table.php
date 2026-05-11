<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-group notification subscription preferences.
 *
 * Lets a user say "I want all messages in Aliya but only mentions elsewhere."
 * Pairs with tos_notification_preferences (which is global per category × channel)
 * and tos_brand_group_members.muted_until (which is a temporary snooze).
 *
 * mode semantics:
 *   - 'all'           → notify on every new message in this group
 *   - 'mentions_only' → notify only when @mentioned (default for new memberships)
 *   - 'muted'         → never notify (still appears in chat list, unread badge still shown)
 *
 * muted_until:
 *   - Temporary snooze that overrides mode while active (e.g., "mute Aliya for 4 hours")
 *   - When NOW() > muted_until, the row reverts to honoring `mode` again
 *   - Distinct from `mode='muted'` which is a permanent preference
 *
 * Auto-creation:
 *   - On insert into tos_brand_group_members, a subscriptions row is auto-created
 *     with mode='mentions_only' (the safe default) via a model observer.
 *   - Soft-removal of membership does NOT delete the subscription row (preserves
 *     preference if user is re-added later).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_user_group_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('brand_group_id')
                ->constrained('tos_brand_groups')
                ->cascadeOnDelete();

            $table->enum('mode', ['all', 'mentions_only', 'muted'])
                ->default('mentions_only');

            // Temporary snooze; auto-expires
            $table->timestamp('muted_until')->nullable();

            $table->timestamps();

            // One row per (user, group). Leftmost prefix covers user_id-only
            // lookups ("all my subscriptions"), and the FK on user_id is also
            // auto-indexed by MariaDB — no separate user_id index added.
            $table->unique(['user_id', 'brand_group_id']);

            // Cron job that auto-clears expired snoozes uses this:
            //   WHERE muted_until IS NOT NULL AND muted_until < NOW()
            $table->index('muted_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_user_group_subscriptions');
    }
};
