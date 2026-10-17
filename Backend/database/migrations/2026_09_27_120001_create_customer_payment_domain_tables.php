<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('provider_code', 64)->index();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('description_ar', 500)->nullable();
            $table->string('description_en', 500)->nullable();
            $table->string('instructions_ar', 1000)->nullable();
            $table->string('instructions_en', 1000)->nullable();
            $table->string('channel', 20)->default('online'); // offline|online
            $table->string('flow_type', 20)->default('none'); // none|step1|step2
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('customer_payment_method_id')->nullable()->constrained('customer_payment_methods')->nullOnDelete();
            $table->string('provider_code', 64)->index();
            $table->string('status', 32)->index(); // pending_otp|paid|failed|expired
            $table->string('provider_request_id', 128)->nullable()->index();
            $table->string('provider_purchase_id', 128)->nullable()->index();
            $table->string('provider_reference', 128)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->decimal('amount', 14, 4);
            $table->string('currency_code', 10);
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('shipping_method', 64)->nullable();
            $table->boolean('gift_wrapping_requested')->default(false);
            $table->string('gift_message', 250)->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('provider_payload')->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_method_code', 64)->nullable()->after('shipping_method_name_en');
            $table->string('payment_status', 32)->nullable()->after('payment_method_code')->index();
            $table->string('payment_provider_reference', 128)->nullable()->after('payment_status');
            $table->string('payment_provider_request_id', 128)->nullable()->after('payment_provider_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_method_code',
                'payment_status',
                'payment_provider_reference',
                'payment_provider_request_id',
            ]);
        });

        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('customer_payment_methods');
    }
};
