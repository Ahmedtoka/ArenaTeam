<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User votes on poll options.
 *
 * UNIQUE (poll_option_id, user_id): a user cannot vote on the same option
 * twice (idempotent toggle / no double-counting). DB-enforced.
 *
 * Single-select vs multi-select enforcement:
 *   The DB cannot easily enforce "user votes on at most ONE option in a
 *   single-select poll" — that would require a composite UNIQUE with poll_id
 *   which isn't directly on this row. Enforced at app layer: PollVoteService
 *   reads poll.multi_select before insert; for single-select polls, an
 *   existing vote by the same user on another option in the same poll is
 *   DELETEd in the same transaction as the new INSERT.
 *
 * Cascade behavior: deleting an option (rare — options are immutable) or a
 * user removes their votes. Deleting the poll cascades through poll_options
 * to votes automatically.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_poll_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('poll_option_id')
                ->constrained('tos_poll_options')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['poll_option_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_poll_votes');
    }
};
