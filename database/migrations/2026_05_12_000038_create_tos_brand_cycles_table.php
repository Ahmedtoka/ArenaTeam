<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brand cycles — 7-step monthly pipeline per brand.
 *
 * stages JSON shape (documented for BrandCycle model; not DB-enforced):
 *   {
 *     strategy:    {status: 'pending|active|completed', started_at, completed_at, owner_id},
 *     action_plan: {...},
 *     design:      {...},
 *     shoot:       {...},
 *     publish:     {...},
 *     ads:         {...},
 *     report:      {...}
 *   }
 *
 * cycle_label is freeform ("May 2026", "Q2 2026", "Aliya Eid Campaign"). UNIQUE
 * within a brand to prevent duplicate labels.
 *
 * Cascade: brand_id RESTRICT (containment). account_manager_id nullable + nullOnDelete.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_brand_cycles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT
            $table->string('cycle_label', 100);
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->json('stages');
            $table->foreignId('account_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Prevent duplicate labels per brand
            $table->unique(['brand_id', 'cycle_label']);

            // Date-ordered cycles per brand
            $table->index(['brand_id', 'cycle_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_brand_cycles');
    }
};
