<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web Push subscriptions per user-device for PWA push notifications.
 *
 * `endpoint` is stored as TEXT (URLs can exceed VARCHAR-indexable length).
 * `endpoint_hash` is a SHA-256 of the endpoint, set in the model's
 * `saving` event, and used as the deterministic uniqueness key alongside user_id.
 *
 * `device_label` is derived from user_agent (e.g. "Chrome on Windows") so users
 * can review and revoke individual devices in settings.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('endpoint');
            $table->string('endpoint_hash', 64); // SHA-256 hex, set in PushSubscription::booted()

            $table->string('public_key')->nullable();      // base64 p256dh (~88 chars)
            $table->string('auth_token', 64)->nullable();  // base64 auth (~24 chars)
            $table->string('content_encoding', 32)->nullable();

            $table->string('user_agent', 500)->nullable();
            $table->string('device_label', 50)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // user_id auto-indexed via FK constraint.
            // Composite unique enables idempotent re-subscribe + per-device revoke.
            $table->unique(['user_id', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_push_subscriptions');
    }
};
