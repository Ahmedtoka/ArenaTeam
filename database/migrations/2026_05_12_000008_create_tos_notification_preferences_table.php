<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user notification preferences: one row per (category × channel).
 *
 * Categories (6):
 *   - direct_message   future DM feature
 *   - mention          @mentions AND Red-question waiting reminders
 *   - chat_message     non-mention messages in groups subscribed to in "all" mode
 *   - task_update      assigned, due-soon, clarified, completed, approved
 *   - meeting          scheduled, starting-soon, cancelled
 *   - digest           daily 8 AM personal + 6 PM team summary
 *
 * Channels (4):
 *   - database         in-app notification feed
 *   - broadcast        Reverb real-time push to open clients
 *   - mail             email digests + immediate critical alerts
 *   - webpush          PWA push to mobile/desktop
 *
 * On user creation, a UserObserver seeds all 24 rows with enabled=true so the
 * invariant "row missing = bug" holds (never "row missing = silently disabled").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('category', [
                'direct_message',
                'mention',
                'chat_message',
                'task_update',
                'meeting',
                'digest',
            ]);

            $table->enum('channel', [
                'database',
                'broadcast',
                'mail',
                'webpush',
            ]);

            $table->boolean('enabled')->default(true);

            $table->timestamps();

            // user_id is covered by FK auto-index AND the leftmost-prefix of the
            // unique constraint below, so no separate user_id index is needed.
            $table->unique(['user_id', 'category', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_notification_preferences');
    }
};
