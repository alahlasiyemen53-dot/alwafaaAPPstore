<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_notifications', function (Blueprint $table): void {
            $table->timestamp('scheduled_at')->nullable()->after('queued_at');
            $table->index(['scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('business_notifications', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_at', 'status']);
            $table->dropColumn('scheduled_at');
        });
    }
};
