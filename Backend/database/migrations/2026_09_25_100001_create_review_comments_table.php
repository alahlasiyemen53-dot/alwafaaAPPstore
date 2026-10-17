<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: MySQL DDL may leave the table behind if a later step failed.
        if (Schema::hasTable('review_comments')) {
            return;
        }

        Schema::create('review_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('product_reviews')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('approved');
            $table->timestamps();

            $table->index('status');
            $table->index(['review_id', 'status', 'created_at'], 'review_comments_public_list_idx');
            $table->index('customer_id');
            $table->index('admin_user_id');
        });

        // Author XOR is enforced in ReviewComment::booted() (application layer).
        // MySQL/MariaDB on this host rejects CHECK constraints on these FK columns
        // (SQLSTATE 1901), so no DB CHECK is added here.
    }

    public function down(): void
    {
        Schema::dropIfExists('review_comments');
    }
};
