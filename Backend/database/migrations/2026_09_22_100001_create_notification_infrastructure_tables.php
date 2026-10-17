<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->morphs('owner');
            $table->string('provider', 30);
            $table->text('token')->nullable();
            $table->char('token_hash', 64)->nullable();
            $table->string('device_identifier', 191)->nullable();
            $table->string('platform', 20);
            $table->string('locale', 20)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 100)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'token_hash']);
            $table->index('token_hash');
            $table->index(['owner_type', 'owner_id', 'provider', 'status']);
        });

        Schema::create('business_notifications', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->morphs('recipient');
            $table->string('type', 50);
            $table->string('category', 30);
            $table->string('channel', 30);
            $table->string('status', 20)->default('pending');
            $table->string('title', 120);
            $table->string('body', 500);
            $table->json('payload');
            $table->unsignedSmallInteger('payload_version')->default(1);
            $table->string('business_reference', 100)->nullable();
            $table->char('idempotency_key', 64)->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_category', 50)->nullable();
            $table->string('failure_summary', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'category', 'channel', 'created_at']);
            $table->index('business_reference');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('business_notification_id')->constrained()->restrictOnDelete();
            $table->foreignId('push_device_id')->constrained()->restrictOnDelete();
            $table->string('channel', 30);
            $table->string('provider', 30);
            $table->string('status', 20)->default('pending');
            $table->boolean('retryable')->default(false);
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_category', 50)->nullable();
            $table->string('failure_summary', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_notification_id', 'push_device_id'], 'notification_delivery_device_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('notification_delivery_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 20);
            $table->string('failure_category', 50)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('failure_summary', 255)->nullable();
            $table->timestamp('attempted_at');
            $table->timestamps();

            $table->unique(['notification_delivery_id', 'attempt_number'], 'notification_attempt_number_unique');
            $table->index(['notification_delivery_id', 'attempted_at'], 'notification_attempt_delivery_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('business_notifications');
        Schema::dropIfExists('push_devices');
    }
};
