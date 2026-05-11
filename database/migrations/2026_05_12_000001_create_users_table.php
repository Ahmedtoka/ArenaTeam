<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Standard Laravel auth
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Contact + identity
            $table->string('phone', 20)->nullable();
            $table->string('job_title', 100)->nullable();

            // Department + permission tier
            $table->enum('department', [
                'accounts', 'design', 'photo', 'web', 'dev',
                'moderation', 'ads', 'creative',
            ])->nullable();
            $table->enum('team_role', [
                'owner', 'department_manager', 'account_manager', 'employee',
            ])->default('employee');

            // Reporting hierarchy (self-FK, nullOnDelete preserves history if a manager hard-deletes)
            $table->foreignId('reports_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Visual identity
            $table->string('avatar_path', 255)->nullable();
            $table->string('avatar_color', 7)->nullable(); // hex like "#4F46E5"

            // Localization
            $table->string('locale', 5)->default('ar');
            $table->string('timezone', 50)->nullable(); // null => app default 'Africa/Cairo'

            // Status + presence
            $table->string('status_message', 140)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->date('hired_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            // Note: reports_to_id auto-indexed by FK constraint (MariaDB requirement).
            // We add single-column indexes on department + team_role for "all designers regardless of status"
            // queries, and composite indexes for the more common "active users in dept X" queries.
            $table->index('department');
            $table->index('team_role');
            $table->index(['department', 'is_active']);
            $table->index(['team_role', 'is_active']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
