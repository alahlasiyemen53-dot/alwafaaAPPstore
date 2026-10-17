<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('action_type', 32)->default('none');
            $table->string('action_reference')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'idx_home_banners_active_sort');
            $table->index('starts_at', 'idx_home_banners_starts_at');
            $table->index('ends_at', 'idx_home_banners_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_banners');
    }
};
