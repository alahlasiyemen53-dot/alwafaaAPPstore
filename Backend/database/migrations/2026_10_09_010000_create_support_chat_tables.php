<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->string('last_message_preview', 255)->nullable();
            $table->timestamp('customer_last_read_at')->nullable();
            $table->timestamp('support_last_read_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'last_message_at'], 'sc_conv_customer_status_msg');
            $table->index(['assigned_admin_id', 'status', 'last_message_at'], 'sc_conv_admin_status_msg');
        });

        Schema::create('support_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('sender_type', 20);
            $table->unsignedBigInteger('sender_id');
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->string('client_message_id', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['conversation_id', 'sender_type', 'sender_id', 'client_message_id'],
                'sc_msg_idempotent'
            );
            $table->index(['conversation_id', 'created_at'], 'sc_msg_conv_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_chat_messages');
        Schema::dropIfExists('support_conversations');
    }
};
