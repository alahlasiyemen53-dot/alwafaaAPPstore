<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('calling_code', 8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $yemenId = DB::table('countries')->insertGetId([
            'code' => 'YE',
            'name_ar' => 'اليمن',
            'name_en' => 'Yemen',
            'calling_code' => '+967',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('governorates', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('id')->constrained('countries')->nullOnDelete();
            $table->index('country_id', 'governorates_country_id_index');
        });

        DB::table('governorates')->whereNull('country_id')->update(['country_id' => $yemenId]);

        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('customer_id')->constrained('countries')->nullOnDelete();
            $table->index('country_id', 'customer_addresses_country_id_index');
        });

        if (Schema::hasColumn('customer_addresses', 'governorate_id')) {
            $pairs = DB::table('customer_addresses as a')
                ->join('governorates as g', 'g.id', '=', 'a.governorate_id')
                ->whereNull('a.country_id')
                ->whereNotNull('g.country_id')
                ->select('a.id', 'g.country_id')
                ->get();

            foreach ($pairs as $pair) {
                DB::table('customer_addresses')->where('id', $pair->id)->update(['country_id' => $pair->country_id]);
            }

            DB::table('customer_addresses')
                ->whereNull('country_id')
                ->update(['country_id' => $yemenId]);
        }
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropIndex('customer_addresses_country_id_index');
            $table->dropColumn('country_id');
        });

        Schema::table('governorates', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropIndex('governorates_country_id_index');
            $table->dropColumn('country_id');
        });

        Schema::dropIfExists('countries');
    }
};
