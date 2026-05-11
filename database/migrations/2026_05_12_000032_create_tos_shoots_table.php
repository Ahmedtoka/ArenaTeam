<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo / video shoots — first-class entities for the Photo & Reels department.
 *
 * Linked downstream to tos_shoot_outputs (raw → editing → review → done pipeline).
 * Often spawn a "Shoot · {date}" brand_group for coordination (no DB linkage;
 * groups are referenced by convention in the UI).
 *
 * Cancellation audit follows the established pattern (cancelled_at, cancelled_by_id,
 * cancellation_reason) — same as meetings and tasks.
 *
 * collaborators JSON: array of user IDs who participated beyond the lead. The
 * Shoot model casts to array; eager-load User records when rendering.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_shoots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT — keep
            $table->foreignId('lead_photographer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->enum('type', ['lifestyle', 'product', 'editorial', 'commercial', 'event', 'reel', 'bts']);

            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();

            $table->json('collaborators')->nullable();

            $table->unsignedInteger('expected_outputs')->default(0);
            $table->unsignedInteger('delivered_outputs')->default(0);

            $table->enum('status', ['scheduled', 'in_progress', 'shot', 'editing', 'delivered', 'cancelled'])
                ->default('scheduled');

            // Cancellation audit
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();

            $table->timestamps();

            // Calendar query: "shoots this week" — WHERE scheduled_date BETWEEN ?
            $table->index('scheduled_date');
            // Brand timeline of shoots
            $table->index(['brand_id', 'scheduled_date']);
            // Status filtering ("shoots currently in editing")
            $table->index(['status', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_shoots');
    }
};
