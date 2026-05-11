<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creative team performance ratings — AM-given scores on concepts/strategies/captions.
 *
 * Hybrid measurement for the creative department where output is qualitative
 * (a strategy is "great" or "weak," not "8 posts delivered"). AMs rate creative
 * output 1–5 on each deliverable type.
 *
 * rating: TINYINT UNSIGNED, values 1–5. Validated at app layer (Laravel
 * validation rule between:1,5). No DB CHECK constraint — they're brittle
 * across MariaDB versions and Laravel doesn't have a clean Blueprint API.
 *
 * Cascade: actor columns nullable + nullOnDelete. brand_id RESTRICT.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_creative_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rated_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('rated_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tos_tasks')
                ->nullOnDelete();
            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT

            $table->string('deliverable_type'); // 'concept', 'strategy', 'caption', 'mood_board', etc.
            $table->unsignedTinyInteger('rating'); // 1-5, validated app-side
            $table->text('feedback')->nullable();

            $table->timestamps();

            // Per-user performance roll-up
            $table->index(['rated_user_id', 'created_at']);
            // Per-brand creative-quality view
            $table->index(['brand_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_creative_ratings');
    }
};
