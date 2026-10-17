<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->uuid('challenge_id')->unique();
            $table->string('phone', 32)->nullable();
            $table->string('normalized_phone', 20)->index();
            $table->string('purpose', 32)->index();
            $table->string('code_hash', 255);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['normalized_phone', 'purpose', 'status'], 'idx_otp_phone_purpose_status');
            $table->index(['expires_at', 'status'], 'idx_otp_expires_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_otp_challenges');
    }
};
