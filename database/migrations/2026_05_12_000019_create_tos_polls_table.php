<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poll metadata. One row per message of type='poll'.
 *
 * 1-to-1 with host message (DB-enforced via UNIQUE on message_id):
 *   - On creating a poll-type message, the corresponding tos_polls row is
 *     inserted in the same DB transaction.
 *   - On deleting the message (or its parent group), the poll cascades.
 *
 * multi_select: true → voters may pick multiple options; false → exactly one.
 *
 * closes_at: optional auto-close. Scheduled job AutoClosePolls marks past-close
 * polls as read-only at the app layer (does not delete; voted options remain
 * visible for the final tally).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_polls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->unique() // 1-to-1 with host message
                ->constrained('tos_messages')
                ->cascadeOnDelete();

            $table->string('question');
            $table->boolean('multi_select')->default(false);
            $table->timestamp('closes_at')->nullable();

            $table->timestamps();

            // AutoClosePolls scheduled job: WHERE closes_at IS NOT NULL AND closes_at < NOW()
            $table->index('closes_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_polls');
    }
};
