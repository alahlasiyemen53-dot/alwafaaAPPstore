<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('gift_wrapping_requested')->default(false)->after('notes');
            $table->text('gift_message_snapshot')->nullable()->after('gift_wrapping_requested');
            $table->index('gift_wrapping_requested');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['gift_wrapping_requested']);
            $table->dropColumn(['gift_wrapping_requested', 'gift_message_snapshot']);
        });
    }
};
