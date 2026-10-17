<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->decimal('delivery_fee', 14, 4)->default(0.0000)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropColumn('delivery_fee');
        });
    }
};
