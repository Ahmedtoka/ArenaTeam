<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messages — the chat backbone. Single most queried table in the system.
 *
 * Threading model (3 self-FKs):
 *  - reply_to_id        — direct parent (this message is a reply to that one)
 *  - thread_root_id     — materialized root of the thread (O(1) thread queries)
 *  - orange_reply_message_id — the reply that flipped a Red message to Orange
 *  Set thread_root_id in Message::saving event:
 *    if (reply_to_id) thread_root_id = (reply_to_id->thread_root_id ?? reply_to_id)
 *    else thread_root_id = own id (requires post-insert UPDATE since id isn't known on insert).
 *
 * Red/Orange/Green importance workflow:
 *  - importance_status moves: normal → red → orange → green (or normal ↔ red)
 *  - markers (importance_marked_by, marked_red/orange/green_at) capture transitions
 *  - The R/O/G timestamps are the audit trail; the enum is the current state for fast filtering
 *
 * Mentions:
 *  - NOT a JSON column. See tos_message_mentions for the join table.
 *  - Reason: the "Red messages mentioning me" inbox query is the hottest path
 *    in the app; JSON_CONTAINS scans, indexed (message_id, user_id) seeks.
 *
 * Soft delete:
 *  - Uses Laravel's softDeletes() trait. Default scope filters deleted.
 *  - deleted_by_id captures the actor (sender OR moderator).
 *  - UI shows "[deleted message]" placeholder via Model::withTombstones() scope.
 *
 * Search:
 *  - FULLTEXT(body) supported on InnoDB since MariaDB 10.0.5 (this DB is 10.4.32).
 *
 * Indexes intentionally NOT added (verified by recommendation in design review):
 *  - per-message reaction/attachment/reply counters — eager-load instead in v1.
 *  - edit history — only edited_at flag; ALTER later if needed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_messages', function (Blueprint $table) {
            $table->id();

            // Containment + authorship
            $table->foreignId('brand_group_id')
                ->constrained('tos_brand_groups')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users'); // RESTRICT — message must have a sender; users are soft-deleted so this never blocks in practice

            // Threading (self-FKs, all nullable)
            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();
            $table->foreignId('thread_root_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();

            // Message kind — flat 10-value enum (KISS); kind/type split rejected per design review
            $table->enum('type', [
                'text', 'voice', 'file', 'image', 'poll',
                'system', 'task_card', 'meeting_card', 'task_done', 'clarification_request',
            ]);

            // Content
            $table->text('body')->nullable();         // 64KB max; app-layer caps at 20K chars
            $table->json('payload')->nullable();      // type-specific (voice URL+duration, poll opts, linked task_id/meeting_id, etc.)

            // Red/Orange/Green importance workflow
            $table->enum('importance_status', ['normal', 'red', 'orange', 'green'])
                ->default('normal');
            $table->foreignId('importance_marked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('marked_red_at')->nullable();
            $table->timestamp('marked_orange_at')->nullable();
            $table->timestamp('marked_green_at')->nullable();
            $table->foreignId('orange_reply_message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();

            // Edits
            $table->timestamp('edited_at')->nullable();

            // Deletes — Laravel softDeletes() + audit actor
            $table->softDeletes();
            $table->foreignId('deleted_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Indexes
            // Note: brand_group_id, user_id, reply_to_id, thread_root_id, importance_marked_by,
            // orange_reply_message_id, deleted_by_id all auto-indexed by their FK constraints.

            // Chat list pagination (no soft-delete filter)
            $table->index(['brand_group_id', 'created_at'], 'tos_messages_chat_index');

            // Chat list with SoftDeletes scope: WHERE brand_group_id=? AND deleted_at IS NULL ORDER BY created_at
            // (Partly redundant with the previous index via leftmost prefix, but kept per spec for
            // explicit naming and covering-index behavior when deleted_at is in the SELECT.)
            $table->index(['brand_group_id', 'created_at', 'deleted_at'], 'tos_messages_chat_active_index');

            // Red-queue per brand: WHERE importance_status='red' AND brand_group_id=?
            $table->index(['importance_status', 'brand_group_id'], 'tos_messages_importance_index');

            // "Messages I sent that are still Red": WHERE user_id=? AND importance_status='red'
            $table->index(['user_id', 'importance_status'], 'tos_messages_sender_importance_index');

            // Full-text search (InnoDB FULLTEXT on MariaDB 10.0.5+)
            $table->fullText('body', 'tos_messages_body_fulltext');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_messages');
    }
};
