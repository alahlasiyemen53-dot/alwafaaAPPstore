<?php

use App\Support\GovernorateDeliveryMethodBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorate_delivery_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->foreignId('delivery_method_id')->constrained('delivery_methods')->cascadeOnDelete();
            $table->decimal('delivery_fee', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['governorate_id', 'delivery_method_id'], 'gov_dm_unique');
            $table->index('governorate_id');
            $table->index('delivery_method_id');
            $table->index('is_active');
        });

        GovernorateDeliveryMethodBackfill::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('governorate_delivery_methods');
    }
};
