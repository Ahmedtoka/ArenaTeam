<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks — the unit of work tracked through full lifecycle KPIs.
 *
 * Second-most complex table in the system. Drives every department dashboard,
 * the personal MyTasks screen, the inbox, and the KPI rollup engine.
 *
 * State machine — status and lifecycle timestamps MUST stay in sync.
 * Source of truth for UI: `status`. Source of truth for KPIs: timestamps.
 * Both move together in TaskLifecycleService (Phase B). Invariants:
 *
 *   status='pending'                → all lifecycle timestamps NULL
 *   status='in_progress'            → first_opened_at + started_working_at SET
 *   status='awaiting_clarification' → clarification_requested_at SET, clarification_replied_at NULL
 *   status='resumed'                → clarification_replied_at + resumed_work_at SET
 *   status='done'                   → completed_at SET, approved_at NULL
 *   status='approved'               → completed_at + approved_at + approved_by_id SET
 *   status='cancelled'              → cancelled_at + cancelled_by_id SET
 *   status='archived'               → archived_at + archived_by_id SET
 *
 * The two clarification fields (requested_at, replied_at) reset across cycles
 * (revision_count increments each time). Per-revision history is in tos_task_revisions.
 *
 * FK cascade behavior (deviates from spec where convention demands):
 *   - brand_id              RESTRICT       (tasks shouldn't vanish if brand hard-deletes;
 *                                           brand churn uses status='churned' instead)
 *   - assigned_to_id        nullable + nullOnDelete (history-preserving actor)
 *   - created_by_id         nullable + nullOnDelete
 *   - approved_by_id        nullable + nullOnDelete
 *   - cancelled_by_id       nullable + nullOnDelete
 *   - archived_by_id        nullable + nullOnDelete
 *   - source_message_id     nullOnDelete   (per spec)
 *   - source_meeting_id     nullOnDelete   (per spec)
 *   - clarification_request_message_id  nullOnDelete (per spec)
 *
 * department: ENUM matching users.department exactly. DB-enforced consistency.
 *
 * deliverables JSON shape (documented for Task model; not DB-enforced):
 *   [{type: 'post', qty: 3, done: 2}, {type: 'story', qty: 6, done: 6}, ...]
 *
 * Sub-tasks NOT supported in v1 — flat tasks only. Use tags or related-task
 * conventions if grouping needed.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_tasks', function (Blueprint $table) {
            $table->id();

            // Containment + actors
            $table->foreignId('brand_id')->constrained('brands'); // RESTRICT — keep
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Source linkages (where this task came from)
            $table->foreignId('source_message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();
            $table->foreignId('source_meeting_id')
                ->nullable()
                ->constrained('tos_meetings')
                ->nullOnDelete();

            // Content
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Categorization
            $table->enum('department', [
                'accounts', 'design', 'photo', 'web', 'dev',
                'moderation', 'ads', 'creative',
            ]);
            $table->json('tags')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->timestamp('due_at')->nullable();

            // Lifecycle timestamps — see state machine docblock above
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('started_working_at')->nullable();
            $table->timestamp('clarification_requested_at')->nullable();
            $table->foreignId('clarification_request_message_id')
                ->nullable()
                ->constrained('tos_messages')
                ->nullOnDelete();
            $table->timestamp('clarification_replied_at')->nullable();
            $table->timestamp('resumed_work_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Status
            $table->enum('status', [
                'pending', 'in_progress', 'awaiting_clarification',
                'resumed', 'done', 'approved', 'archived', 'cancelled',
            ])->default('pending');

            $table->unsignedInteger('revision_count')->default(0);
            $table->json('deliverables')->nullable();

            // Cancellation audit
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('cancellation_reason', 255)->nullable();

            // Archival audit
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes — all back hot dashboard queries
            $table->index(['assigned_to_id', 'status']);     // "my open tasks"
            $table->index(['brand_id', 'status']);            // "active tasks per brand"
            $table->index(['department', 'due_at']);          // department overview
            $table->index(['status', 'due_at']);              // org-wide overdue alert
            $table->index(['status', 'completed_at']);        // KPI rollups by completion
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_tasks');
    }
};
