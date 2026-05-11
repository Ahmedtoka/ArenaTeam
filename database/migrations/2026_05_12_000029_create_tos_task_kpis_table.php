<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalized KPI read-model for tasks. One row per task (1-to-1 via UNIQUE).
 *
 * Written by RecalculateTaskKpis job whenever tos_tasks lifecycle timestamps
 * change. Read by department dashboards, employee performance pages, and
 * brand-cycle rollups.
 *
 * Derived from tos_tasks columns:
 *   time_to_open_seconds            = first_opened_at - created_at
 *   time_to_start_seconds           = started_working_at - first_opened_at
 *   working_seconds                 = completed_at - started_working_at - clarification_wait
 *   total_clarification_wait_seconds = SUM(replied_at - requested_at) across revisions
 *   total_cycle_seconds             = completed_at - created_at
 *   revision_count                  = mirrored from tos_tasks.revision_count
 *   was_overdue                     = completed_at > due_at (when both set)
 *
 * 1-to-1 via UNIQUE on task_id. Cascade on task delete (KPIs are useless without the task).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_task_kpis', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->unique()
                ->constrained('tos_tasks')
                ->cascadeOnDelete();

            $table->integer('time_to_open_seconds')->nullable();
            $table->integer('time_to_start_seconds')->nullable();
            $table->integer('working_seconds')->nullable();
            $table->integer('total_clarification_wait_seconds')->default(0);
            $table->integer('total_cycle_seconds')->nullable();
            $table->unsignedInteger('revision_count')->default(0);
            $table->boolean('was_overdue')->default(false);

            $table->timestamps();

            // Aggregate queries (e.g., "average cycle time by department"):
            //   join tos_tasks; the indexes on tos_tasks carry the filter weight.
            // Per-KPI lookups are by task_id (UNIQUE) — already covered.
            $table->index('was_overdue'); // "all overdue completed tasks" rollup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_task_kpis');
    }
};
