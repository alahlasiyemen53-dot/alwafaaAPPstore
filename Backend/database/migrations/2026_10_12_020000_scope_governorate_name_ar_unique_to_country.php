<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('governorates')) {
            Schema::table('governorates', function (Blueprint $table): void {
                // Drop global unique index on name_ar to allow same governorate/region names in different countries
                try {
                    $table->dropUnique('governorates_name_ar_unique');
                } catch (\Throwable $e) {
                    // Ignore if already dropped
                }

                $table->unique(['country_id', 'name_ar'], 'governorates_country_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('governorates')) {
            Schema::table('governorates', function (Blueprint $table): void {
                try {
                    $table->dropUnique('governorates_country_name_unique');
                } catch (\Throwable $e) {
                    // Ignore
                }

                try {
                    $table->unique('name_ar', 'governorates_name_ar_unique');
                } catch (\Throwable $e) {
                    // Ignore
                }
            });
        }
    }
};
