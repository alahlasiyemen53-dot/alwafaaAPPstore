<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // percentage, fixed_amount, fixed_price
            $table->decimal('value', 12, 4);
            $table->string('scope'); // all, product, variant, category, brand
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('priority')->default(0)->index();
            $table->string('stacking_policy')->default('none');
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('promotion_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('target_type'); // product, variant, category, brand
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['promotion_id', 'target_type', 'target_id'], 'prom_target_unique');
            $table->index(['target_type', 'target_id'], 'prom_target_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_targets');
        Schema::dropIfExists('promotions');
    }
};
