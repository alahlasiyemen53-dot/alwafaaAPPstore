<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('countries', 'iso_code') && ! Schema::hasColumn('countries', 'code')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->renameColumn('iso_code', 'code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('countries', 'code') && ! Schema::hasColumn('countries', 'iso_code')) {
            Schema::table('countries', function (Blueprint $table): void {
                $table->renameColumn('code', 'iso_code');
            });
        }
    }
};
