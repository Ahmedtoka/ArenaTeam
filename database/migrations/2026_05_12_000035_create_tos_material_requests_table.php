<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material requests — discrete asks made within a campaign pipeline.
 *
 * Examples: "Need 3 lifestyle posts for May 15 launch", "Need product shots
 * for new SKU." Each request can be promoted to a full tos_tasks row when
 * picked up by the design/photo team.
 *
 * Cascade: pipeline_id cascade. task_id nullOnDelete.
 *
 * references JSON: array of inspiration URLs / mood-board references.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_material_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pipeline_id')
                ->constrained('tos_material_pipelines')
                ->cascadeOnDelete();
            $table->foreignId('requested_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('description');
            $table->json('references')->nullable();

            $table->enum('status', ['pending', 'in_creative', 'in_design', 'ready', 'rejected'])
                ->default('pending');

            $table->foreignId('task_id')
                ->nullable()
                ->constrained('tos_tasks')
                ->nullOnDelete();

            $table->timestamps();

            // "Pending requests on this pipeline"
            $table->index(['pipeline_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_material_requests');
    }
};
