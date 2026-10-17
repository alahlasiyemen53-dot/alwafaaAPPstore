<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tier_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_tier_id')->constrained('customer_tiers')->restrictOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->string('assignment_source', 50)->default('SYSTEM_DEFAULT');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'ends_at'], 'idx_customer_tier_memberships_current');
            $table->index(['customer_tier_id', 'ends_at'], 'idx_tier_memberships_active_count');
            $table->index(['customer_id', 'starts_at'], 'idx_customer_tier_memberships_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tier_memberships');
    }
};
