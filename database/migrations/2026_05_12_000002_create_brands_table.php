<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);
            $table->string('slug', 100)->unique();

            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable(); // policy-gated to owner + AMs at app layer

            // Visual identity
            $table->string('logo_path', 255)->nullable();
            $table->string('primary_color', 7)->nullable();   // e.g. "#3C3489"
            $table->string('secondary_color', 7)->nullable();

            // Default account manager (nullOnDelete so brand survives if AM hard-deletes)
            $table->foreignId('account_manager_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Operational metadata
            $table->string('whatsapp_number', 20)->nullable();
            $table->timestamp('onboarded_at')->nullable();

            $table->enum('status', ['active', 'paused', 'archived', 'churned'])
                ->default('active');

            $table->timestamps();

            // Indexes
            // Note: account_manager_id auto-indexed by FK constraint.
            // slug already indexed by ->unique().
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
