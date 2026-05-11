<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds last_read_message_id FK on tos_brand_group_members → tos_messages.id.
 *
 * Deferred from Group 2 because tos_messages didn't exist yet (a forward FK
 * would have been required, which we avoid). Now that tos_messages is
 * created, we can constrain.
 *
 * Semantics:
 *   - NULL: user has never opened this group's chat
 *   - non-NULL: ID of the most-recent message the user has scrolled past
 *     (the "you've seen up to here" pointer)
 *
 * Used by:
 *   - Precise unread count: tos_messages.id > last_read_message_id (better
 *     than timestamp comparison when multiple messages share a created_at)
 *   - "Jump to where I left off" UX when re-entering a chat
 *
 * Kept in sync with last_read_at by the model (both updated together on
 * read-state changes — see BrandGroupMembership::markRead()).
 *
 * On message hard-delete (rare; soft-delete is the norm), the FK sets the
 * pointer to NULL — UX falls back to last_read_at + first-unread inference.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tos_brand_group_members', function (Blueprint $table) {
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->after('last_read_at')
                ->constrained('tos_messages')
                ->nullOnDelete();

            // FK auto-indexes last_read_message_id — no extra index needed
        });
    }

    public function down(): void
    {
        Schema::table('tos_brand_group_members', function (Blueprint $table) {
            $table->dropForeign(['last_read_message_id']);
            $table->dropColumn('last_read_message_id');
        });
    }
};
