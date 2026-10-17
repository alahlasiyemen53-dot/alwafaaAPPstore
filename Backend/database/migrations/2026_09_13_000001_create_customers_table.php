<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('normalized_phone', 32)->unique();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('phone_verified_at')->nullable()->index();
            $table->string('phone_verification_method', 16)->nullable()->index();
            $table->foreignId('phone_verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['phone_verified_at', 'phone_verification_method']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
