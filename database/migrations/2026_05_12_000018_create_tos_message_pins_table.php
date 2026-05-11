<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pinned messages in a brand group — backs the R/O/G workflow's Green outcome.
 *
 * Created automatically by ImportanceService::markGreen(). The presence of a
 * pin row indicates the message is currently pinned in the group's sidebar.
 *
 * Note on pinned_by FK behavior: deviates from the spec's plain ->constrained()
 * (which is RESTRICT). Using nullable + nullOnDelete matches the established
 * convention for history-preserving actor columns throughout TeamOS
 * (importance_marked_by, archived_by_id, deleted_by_id, etc.). If a user is
 * later hard-deleted, the pin survives with NULL pinned_by — preserves the
 * fact that the message is pinned, loses only who initiated it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_message_pins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('tos_messages')
                ->cascadeOnDelete();
            $table->foreignId('brand_group_id')
                ->constrained('tos_brand_groups')
                ->cascadeOnDelete();
            $table->foreignId('pinned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('pinned_at');

            $table->unique(['message_id', 'brand_group_id']);

            // brand_group_id auto-indexed by FK → covers "all pins in group X" query
            // message_id auto-indexed by FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_message_pins');
    }
};
