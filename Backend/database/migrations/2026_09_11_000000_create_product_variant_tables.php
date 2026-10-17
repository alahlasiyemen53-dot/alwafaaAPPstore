<?php

use App\Enums\ProductVariantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('code', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->string('value_ar');
            $table->string('value_en')->nullable();
            $table->string('code', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['attribute_id', 'code'], 'attribute_value_attribute_code_unique');
            $table->unique(['id', 'attribute_id'], 'attribute_value_id_attribute_unique');
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('status', 16)->default(ProductVariantStatus::Active->value)->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['product_id', 'is_default'], 'product_variant_product_default_index');
        });

        Schema::create('product_variant_values', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->foreignId('attribute_value_id');
            $table->foreign(
                ['attribute_value_id', 'attribute_id'],
                'product_variant_values_attribute_value_attribute_foreign',
            )->references(['id', 'attribute_id'])->on('attribute_values')->restrictOnDelete();
            $table->unique(['product_variant_id', 'attribute_id'], 'product_variant_attribute_unique');
            $table->unique(['product_variant_id', 'attribute_value_id'], 'product_variant_value_unique');
            $table->index('attribute_id');
            $table->index(
                ['attribute_value_id', 'attribute_id'],
                'product_variant_value_attribute_index',
            );
        });

        Schema::create('variant_prices', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 18, 4);
            $table->decimal('compare_at_price', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_variant_id', 'currency_id'], 'variant_price_variant_currency_unique');
            $table->index('currency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
