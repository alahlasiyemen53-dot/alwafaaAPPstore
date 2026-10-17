<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->string('phone', 50);
            $table->string('normalized_phone', 50)->unique();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate_number', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->string('status', 30)->default('assigned')->index();

            // Assignment audit
            $table->timestamp('assigned_at')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            // Success audit
            $table->timestamp('delivered_at')->nullable()->index();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();

            // Failure audit
            $table->timestamp('failed_at')->nullable()->index();
            $table->foreignId('failed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('failure_reason_code', 50)->nullable();
            $table->text('failure_notes')->nullable();

            // Warehouse Return audit
            $table->timestamp('returned_at')->nullable()->index();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('receiving_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // COD Operational Facts (Not financial settlement)
            $table->decimal('cod_amount_to_collect', 16, 4)->default(0);
            $table->decimal('cod_collected_amount', 16, 4)->nullable();
            $table->foreignId('cod_currency_id')->nullable()->constrained('currencies')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('drivers');
    }
};
