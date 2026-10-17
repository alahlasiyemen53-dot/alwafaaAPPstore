<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();

            // Customer snapshots
            $table->string('customer_name_snapshot');
            $table->string('customer_phone_snapshot');

            // Financial amounts
            $table->decimal('subtotal', 14, 4)->default(0.0000);
            $table->decimal('discount_amount', 14, 4)->default(0.0000);
            $table->decimal('total', 14, 4)->default(0.0000);

            // Base currency conversion snapshot
            $table->decimal('exchange_rate_snapshot', 16, 8)->default(1.00000000);
            $table->decimal('base_currency_total', 14, 4)->default(0.0000);

            // Notes and Cancellation
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Status actors and timestamps
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['warehouse_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
