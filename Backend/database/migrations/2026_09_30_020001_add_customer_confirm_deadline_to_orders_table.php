<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('customer_confirm_deadline_at')->nullable()->after('confirmed_at');
            $table->timestamp('customer_confirmed_at')->nullable()->after('customer_confirm_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['customer_confirm_deadline_at', 'customer_confirmed_at']);
        });
    }
};
