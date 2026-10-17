<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('provider', 64)->default('deterministic_mock');
            $table->string('model_version', 64)->default('v1.0.0');
            $table->string('embedding_version', 32)->default('v1');
            $table->unsignedSmallInteger('vector_dimension')->default(512);
            $table->string('vector_id', 128)->unique();
            $table->json('embedding_vector')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('product_id');
            $table->index('media_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_embeddings');
    }
};
