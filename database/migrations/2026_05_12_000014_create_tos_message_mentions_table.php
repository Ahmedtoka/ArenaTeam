<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Message mentions — join table for @mentions in messages.
 *
 * Replaces the JSON `mentions` column on tos_messages. The "Red messages
 * mentioning me" inbox query is the hottest path in the app; this join
 * table makes it an indexed seek instead of a JSON_CONTAINS scan over a
 * growing messages table.
 *
 * Append-only: rows are created on message insert (parsed from body or
 * explicit mention selector) and never updated — hence single created_at,
 * no updated_at.
 *
 * notified_at: timestamp when push/email notification was actually sent
 * to the mentioned user. NULL = pending; the SendMentionNotifications
 * scheduled job picks these up.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_message_mentions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('tos_messages')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Inbox hot path: WHERE user_id=? then JOIN tos_messages for the Red filter
            $table->index(['user_id', 'message_id'], 'tos_message_mentions_inbox_index');

            // Pending-notifications scheduled job: WHERE notified_at IS NULL
            $table->index('notified_at');

            // message_id is auto-indexed by its FK constraint
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_message_mentions');
    }
};
