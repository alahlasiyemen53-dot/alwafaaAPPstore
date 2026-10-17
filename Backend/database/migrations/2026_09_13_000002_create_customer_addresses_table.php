<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('customer_addresses');

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 64);
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();

            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();

            $table->string('neighborhood')->nullable();
            $table->string('street')->nullable();
            $table->string('building', 128)->nullable();
            $table->string('floor', 64)->nullable();
            $table->string('apartment', 64)->nullable();
            $table->string('landmark')->nullable();
            $table->text('notes')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('formatted_address', 500)->nullable();

            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
            $table->index(['governorate_id', 'city_id', 'district_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
