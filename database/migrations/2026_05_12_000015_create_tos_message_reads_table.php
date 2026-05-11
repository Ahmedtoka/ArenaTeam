<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user read receipts on individual messages.
 *
 * One row per (message, reader). Distinct from tos_brand_group_members.last_read_at
 * (which is a coarse group-level pointer): this table records "Maria read this
 * exact message at 14:32" — used for granular receipts like read-receipt
 * indicators on important Red messages.
 *
 * Not all messages get read-receipt rows: app records reads selectively
 * (currently: only on Red and direct-mention messages). For general unread
 * counts, use the brand_group_members pointer.
 *
 * No timestamps() — only read_at matters; created_at/updated_at would be
 * effectively duplicates of read_at.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_message_reads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('tos_messages')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamp('read_at');

            $table->unique(['message_id', 'user_id']);

            // user_id auto-indexed by FK — covers "all messages user X has read"
            // message_id auto-indexed by FK — covers "who has read message Y"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_message_reads');
    }
};
