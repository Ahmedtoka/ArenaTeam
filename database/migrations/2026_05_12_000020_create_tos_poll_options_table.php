<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual options within a poll.
 *
 * Created at poll-creation time, immutable thereafter — editing labels mid-poll
 * would invalidate existing votes. Hence no timestamps().
 *
 * sort_order: manual ordering for display. Polls typically have 2–5 options so
 * no composite (poll_id, sort_order) index needed — the FK auto-index plus
 * filesort over a tiny range is essentially free.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_poll_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('poll_id')
                ->constrained('tos_polls')
                ->cascadeOnDelete();

            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);

            // No timestamps — options are immutable
            // poll_id auto-indexed by FK
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_poll_options');
    }
};
