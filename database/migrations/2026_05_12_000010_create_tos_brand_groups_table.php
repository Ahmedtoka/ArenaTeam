<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand groups — the chat-room entity. One per brand minimum (primary group),
 * plus optional sub-groups (Design, Shoot · May 13, Ads, etc.).
 *
 * Soft-deletion semantics:
 *  - `archived_at` IS our soft-delete equivalent (no Laravel deleted_at).
 *  - Default Eloquent scope filters archived_at IS NULL (see BrandGroup::booted()).
 *  - Use ->withArchived() to include them.
 *
 * Auto-archival:
 *  - Shoot groups set `auto_archive_at` to (shoot_date + 7 days) by default.
 *  - Scheduled job ArchiveStaleGroups runs daily, sets archived_at where
 *    auto_archive_at < NOW() AND archived_at IS NULL.
 *  - Primary groups never auto-archive.
 *
 * Denormalized fields for performance:
 *  - `last_message_at` updated by Message::saved event — powers the
 *    WhatsApp-style most-recently-active sort in the chat list.
 *  - `message_count` updated by Message::created / Message::deleted — powers
 *    "Aliya · 1,247 messages this month" without scanning tos_messages.
 *
 * Permission rules (enforced in BrandGroupPolicy, not at the DB layer):
 *   Create sub-group under a brand:
 *     - owner: always
 *     - department_manager: only within brands their team works on
 *     - account_manager: only for brands they manage
 *     - employee: never
 *   Archive a sub-group:
 *     - the row's created_by_id user (creator)
 *     - the brand's account_manager_id
 *     - any department_manager or owner
 *     - employees: never
 *   Primary group cannot be archived directly — archive the brand instead
 *   (which sets brands.status='archived' but does NOT cascade-archive groups;
 *   filtering happens at query time via Brand::isArchived()).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_brand_groups', function (Blueprint $table) {
            $table->id();

            // Ownership + hierarchy
            $table->foreignId('brand_id')
                ->constrained('brands')
                ->cascadeOnDelete();
            $table->foreignId('parent_group_id')
                ->nullable()
                ->constrained('tos_brand_groups')
                ->nullOnDelete();

            // Identity + context
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['primary', 'design', 'shoot', 'ads', 'creative', 'custom'])
                ->default('primary');

            // Audit actors
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('archived_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Lifecycle timestamps
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('auto_archive_at')->nullable(); // populated for shoot/custom groups

            // Denormalized read-model fields (kept in sync via Message events)
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('message_count')->default(0);

            // Manual ordering (primary always sort_order=0 and pinned in UI;
            // enforced at app layer, not DB)
            $table->unsignedInteger('sort_order')->default(0);

            // Free-form configuration (per-group notif rules, custom flags per type, etc.)
            $table->json('settings')->nullable();

            $table->timestamps();

            // Indexes
            // brand_id and parent_group_id are auto-indexed by their FK constraints,
            // but a composite index on (brand_id, parent_group_id) speeds up the
            // common "list top-level groups for brand X" query (parent IS NULL filter).
            $table->index(['brand_id', 'parent_group_id']);

            // Main chat-list query: WHERE brand_id = ? AND archived_at IS NULL
            //   ORDER BY last_message_at DESC LIMIT 20
            // Note on direction: MariaDB reads an ASC composite index BACKWARDS for
            // ORDER BY ... DESC just as efficiently as a true descending index, so
            // the standard ASC composite below covers the chat-list query. If we
            // later need a mixed-direction query that genuinely benefits from a
            // descending column in the index, swap to MariaDB 10.8+ DESC syntax
            // via raw SQL — for now, the optimizer handles it.
            $table->index(['brand_id', 'last_message_at', 'archived_at'], 'tos_brand_groups_chatlist_index');

            // Active-filter scans
            $table->index('archived_at');

            // ArchiveStaleGroups scheduled job query
            $table->index('auto_archive_at');

            // Filter-by-type queries (e.g., "all shoot groups")
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_brand_groups');
    }
};
