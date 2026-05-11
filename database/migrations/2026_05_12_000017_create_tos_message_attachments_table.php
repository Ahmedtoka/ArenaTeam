<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File / image / voice-note / document attachments on messages.
 *
 * Storage convention (set by App\TeamOS\Support\StoragePaths::forMessage):
 *   teamos/messages/{brand_group_id}/{YYYY}/{MM}/{ulid}.{ext}
 *
 * disk: 'local' default. The TEAMOS_DEFAULT_DISK env var flips new uploads
 * to 's3' (or any compatible) without a schema change. Existing rows retain
 * their original disk — App\TeamOS\Models\MessageAttachment::storage()
 * resolves to the row's disk, not the env value.
 *
 * meta JSON examples (kept loose for forward compatibility):
 *   image: {width: 1920, height: 1080, has_thumbnail: true}
 *   voice: {duration_seconds: 47, waveform: [0.2, 0.7, ...]}
 *   file:  {scanned_at: "2026-05-12T14:32Z", scan_clean: true}  (if ClamAV available)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tos_message_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('tos_messages')
                ->cascadeOnDelete();

            $table->string('disk')->default('local'); // resolves via TEAMOS_DEFAULT_DISK for new rows
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->json('meta')->nullable();

            $table->timestamps();

            // message_id auto-indexed by FK; no other indexes needed for v1.
            // If we later need "all images uploaded by user X" we'll ALTER then.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tos_message_attachments');
    }
};
