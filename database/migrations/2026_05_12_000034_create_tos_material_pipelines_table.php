<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material pipelines — campaign-level flow from brief to launched.
 *
 * Each pipeline is a single brand campaign moving through 7 stages:
 *   brief → creative_concept → production → design → material_ready → launched → completed
 *
 * stage_history JSON shape:
 *   [{stage: 'brief', entered_at: '...', by_user_id: 12}, ...]
 *
 * Owner (AM) and media buyer are tracked separately — the AM "owns" the brand
 * relationship; the media buyer handles the ads spend once material is ready.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_material_pipelines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT
            $table->string('campaign_name');
            $table->enum('current_stage', [
                'brief', 'creative_concept', 'production', 'design',
                'material_ready', 'launched', 'completed',
            ])->default('brief');

            $table->foreignId('owner_account_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('media_buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('stage_history')->nullable();

            $table->date('campaign_start')->nullable();
            $table->date('campaign_end')->nullable();

            $table->timestamps();

            // Brand campaign list, ordered by start date
            $table->index(['brand_id', 'campaign_start']);
            // "All pipelines currently in production" type queries
            $table->index('current_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_material_pipelines');
    }
};
