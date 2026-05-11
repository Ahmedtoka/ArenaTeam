<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual outputs from a shoot — reels, photos, stories, BTS clips.
 *
 * Each output flows through raw → editing → review → done. When promoted to an
 * editing task, the row links to tos_tasks.id (nullable; not all outputs become
 * tracked tasks — some are simple deliverables).
 *
 * Cascade: shoot_id cascade. task_id nullOnDelete (unlinking the task keeps
 * the shoot output row intact).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_shoot_outputs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shoot_id')
                ->constrained('tos_shoots')
                ->cascadeOnDelete();
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tos_tasks')
                ->nullOnDelete();

            $table->string('output_type'); // freeform: 'reel', 'photo', 'story', 'bts_clip', etc.
            $table->string('title')->nullable();
            $table->enum('status', ['raw', 'editing', 'review', 'done'])->default('raw');

            $table->timestamps();

            // Status pipeline view: "all outputs currently in editing"
            $table->index(['shoot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_shoot_outputs');
    }
};
