<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_addresses', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('contact_phone')->constrained('countries')->nullOnDelete();
            $table->string('country_name_snapshot')->nullable()->after('district_name_snapshot');
            $table->string('country_code_snapshot', 8)->nullable()->after('country_name_snapshot');
            $table->index('country_id', 'order_addresses_country_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropIndex('order_addresses_country_id_index');
            $table->dropColumn(['country_id', 'country_name_snapshot', 'country_code_snapshot']);
        });
    }
};
