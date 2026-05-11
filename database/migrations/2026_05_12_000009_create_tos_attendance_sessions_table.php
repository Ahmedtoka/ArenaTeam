<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance sessions — one row per check-in/check-out cycle.
 *
 * Design notes:
 *  - Multiple sessions per day allowed (split shifts, late-night shoot wraps).
 *  - `created_by_id` distinguishes self-entry (null OR == user_id) from manager
 *    retroactive entry. Authorization enforced at the policy / model layer:
 *    if created_by_id IS NOT NULL AND created_by_id != user_id, the actor
 *    must have team_role IN ('department_manager', 'owner').
 *  - `auto_closed_at` is set ONLY by the system force-close job (sessions > 14h
 *    open). Distinct from `check_out_at`, which represents intentional check-out.
 *  - `duration_minutes` is denormalized for analytics — set in the model's
 *    saving event whenever both check_in_at and check_out_at (or auto_closed_at)
 *    are present.
 *  - No soft deletes: attendance is immutable audit data. Hard delete is admin-only.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_attendance_sessions', function (Blueprint $table) {
            $table->id();

            // Who this session belongs to (the employee whose attendance this records)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Who created the row (null = self-entry; non-null & != user_id = manager retroactive entry)
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Session lifecycle timestamps
            $table->timestamp('check_in_at'); // required — set by controller
            $table->timestamp('check_out_at')->nullable();
            $table->timestamp('auto_closed_at')->nullable(); // set by ForceCloseStaleSessions job

            // Denormalized duration (minutes) — calculated in model on save when both timestamps present
            $table->integer('duration_minutes')->nullable();

            // Context
            $table->enum('source', ['web', 'pwa', 'mobile_browser', 'manual', 'system'])
                ->default('web');
            $table->enum('location', ['office', 'home', 'on_site', 'traveling', 'other'])
                ->nullable();

            // Audit metadata — check-in
            $table->ipAddress('check_in_ip')->nullable();
            $table->string('check_in_user_agent')->nullable();

            // Audit metadata — check-out (may differ from check-in if user moved locations)
            $table->ipAddress('check_out_ip')->nullable();
            $table->string('check_out_user_agent')->nullable();

            // Free-form annotations: "half-day", "sick — left at 11", "stayed for shoot wrap"
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            // user_id auto-indexed by FK; created_by_id auto-indexed by FK.
            $table->index(['user_id', 'check_in_at']); // main per-user history lookup
            $table->index('check_out_at');             // "who's currently checked in" (NULL scan)
            $table->index(['location', 'check_in_at']); // "who's at office today"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_attendance_sessions');
    }
};
