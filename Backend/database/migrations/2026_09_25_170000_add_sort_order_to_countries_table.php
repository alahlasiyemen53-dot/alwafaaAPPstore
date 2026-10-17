<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table): void {
            if (! Schema::hasColumn('countries', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
                $table->index('sort_order', 'countries_sort_order_index');
            }
        });

        // Yemen first in customer country pickers.
        DB::table('countries')->where('code', 'YE')->update(['sort_order' => 1]);
        DB::table('countries')->where('code', '!=', 'YE')->where('sort_order', 0)->update([
            'sort_order' => DB::raw('id + 10'),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('countries') || ! Schema::hasColumn('countries', 'sort_order')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropIndex('countries_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};
