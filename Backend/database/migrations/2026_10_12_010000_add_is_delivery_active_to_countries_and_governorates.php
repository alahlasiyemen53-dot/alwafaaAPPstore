<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('countries') && ! Schema::hasColumn('countries', 'is_delivery_active')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->boolean('is_delivery_active')->default(false)->after('is_active');
                $table->index(['is_active', 'is_delivery_active'], 'countries_active_delivery_index');
            });

            DB::table('countries')->where('code', 'YE')->update(['is_delivery_active' => true]);
        }

        if (Schema::hasTable('governorates') && ! Schema::hasColumn('governorates', 'is_delivery_active')) {
            Schema::table('governorates', function (Blueprint $table): void {
                $table->boolean('is_delivery_active')->default(true)->after('is_active');
                $table->index(['country_id', 'is_active', 'is_delivery_active'], 'gov_country_active_delivery_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('governorates') && Schema::hasColumn('governorates', 'is_delivery_active')) {
            Schema::table('governorates', function (Blueprint $table): void {
                $table->dropIndex('gov_country_active_delivery_idx');
                $table->dropColumn('is_delivery_active');
            });
        }

        if (Schema::hasTable('countries') && Schema::hasColumn('countries', 'is_delivery_active')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->dropIndex('countries_active_delivery_index');
                $table->dropColumn('is_delivery_active');
            });
        }
    }
};
