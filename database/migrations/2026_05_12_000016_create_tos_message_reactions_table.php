<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emoji reactions on messages.
 *
 * VARCHAR(10) for emoji — comfortably holds standard reactions (👍, ❤️, ✅)
 * and most modifier sequences (👍🏽 with skin tone, ✅ as default presentation).
 * Family/profession emojis with multiple ZWJ-joined glyphs may exceed 10
 * chars; app-layer validation rejects sequences > 10 chars (rare as reactions).
 *
 * UNIQUE (message_id, user_id, emoji): a user can apply multiple distinct
 * emojis to the same message (👍 + ❤️) but cannot apply the same emoji
 * twice. Reaction toggle (tap-to-add, tap-to-remove) at app layer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_message_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('tos_messages')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('emoji', 10);

            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_message_reactions');
    }
};
