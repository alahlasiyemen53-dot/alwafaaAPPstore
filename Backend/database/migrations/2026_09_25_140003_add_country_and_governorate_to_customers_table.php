<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('current_tier_id')->constrained('countries')->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->after('country_id')->constrained('governorates')->nullOnDelete();
            $table->index('country_id', 'customers_country_id_index');
            $table->index('governorate_id', 'customers_governorate_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropForeign(['governorate_id']);
            $table->dropIndex('customers_country_id_index');
            $table->dropIndex('customers_governorate_id_index');
            $table->dropColumn(['country_id', 'governorate_id']);
        });
    }
};
