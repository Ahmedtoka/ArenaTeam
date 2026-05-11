<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designer deliverables ledger — structured equivalent of Fairouz's tracking sheet.
 *
 * One row per delivered asset (or planned asset with status='pending'). Powers
 * the Design department dashboard's monthly aggregation by category & brand.
 *
 * Categories match the existing tracking sheet exactly (16 values per spec).
 *
 * Cascade behavior:
 *   - user_id  nullable + nullOnDelete (history preservation — Fairouz's deliveries
 *              must remain queryable for tenure reports even after she leaves)
 *   - brand_id RESTRICT
 *   - task_id  nullOnDelete (per spec — unlinking task keeps deliverable)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_designer_deliverables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT
            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tos_tasks')
                ->nullOnDelete();

            $table->date('delivered_on');
            $table->enum('category', [
                'post', 'story', 'carousel', 'reel_cover', 'fb_cover',
                'ig_highlight', 'main_banner_web', 'main_banner_mobile',
                'collection_banner', 'pop_up', 'category_design',
                'size_chart', 'printing', 'identity', 'feedback', 'other',
            ]);
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'done'])->default('done');

            $table->timestamps();

            // Per-user monthly aggregation
            $table->index(['user_id', 'delivered_on']);
            // Per-brand monthly aggregation
            $table->index(['brand_id', 'delivered_on']);
            // Category rollups (e.g., "total posts delivered in May")
            $table->index(['category', 'delivered_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_designer_deliverables');
    }
};
