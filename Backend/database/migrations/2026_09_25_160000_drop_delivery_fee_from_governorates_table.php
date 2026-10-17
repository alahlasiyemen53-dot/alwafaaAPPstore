<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('governorates') || ! Schema::hasColumn('governorates', 'delivery_fee')) {
            return;
        }

        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropColumn('delivery_fee');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('governorates') || Schema::hasColumn('governorates', 'delivery_fee')) {
            return;
        }

        Schema::table('governorates', function (Blueprint $table): void {
            $table->decimal('delivery_fee', 14, 4)->default(0)->after('slug');
        });
    }
};
