<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('to_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('rate', 20, 8);
            $table->timestamp('effective_at')->index();
            $table->timestamps();
            $table->unique(['from_currency_id', 'to_currency_id', 'effective_at'], 'exchange_rate_pair_effective_unique');
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar')->unique();
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['governorate_id', 'slug']);
            $table->index('governorate_id');
        });

        Schema::create('districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['city_id', 'slug']);
            $table->index('city_id');
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index('parent_id');
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->foreignId('governorate_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('address_text')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['governorate_id', 'city_id', 'district_id']);
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('governorates');
        Schema::dropIfExists('units');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
