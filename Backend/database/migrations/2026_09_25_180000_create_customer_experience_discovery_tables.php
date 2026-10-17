<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: MySQL DDL auto-commits, so a prior partial run can leave
        // tables behind while this migration remains unrecorded.
        if (! Schema::hasTable('customer_recently_viewed_products')) {
            Schema::create('customer_recently_viewed_products', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamp('last_viewed_at');
                $table->timestamps();

                $table->unique(['customer_id', 'product_id'], 'cust_recently_viewed_unique');
                // MySQL identifier limit is 64 chars; default name exceeds it.
                $table->index(['customer_id', 'last_viewed_at'], 'cust_recently_viewed_at_idx');
            });
        }

        if (! Schema::hasTable('customer_search_history')) {
            Schema::create('customer_search_history', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('query', 191);
                $table->string('normalized_query', 191);
                $table->timestamp('last_searched_at');
                $table->timestamps();

                $table->unique(['customer_id', 'normalized_query'], 'cust_search_history_unique');
                $table->index(['customer_id', 'last_searched_at'], 'cust_search_history_at_idx');
            });
        }

        if (
            Schema::hasTable('business_notifications')
            && ! Schema::hasColumn('business_notifications', 'read_at')
        ) {
            Schema::table('business_notifications', function (Blueprint $table): void {
                $table->timestamp('read_at')->nullable()->after('cancelled_at');
                $table->index(['recipient_type', 'recipient_id', 'read_at'], 'biz_notif_recipient_read_idx');
            });
        }

        if (! Schema::hasTable('product_collections')) {
            Schema::create('product_collections', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('name_ar');
                $table->string('name_en')->nullable();
                $table->string('slug')->unique();
                $table->text('description_ar')->nullable();
                $table->text('description_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'product_collections_active_sort_idx');
            });
        }

        if (! Schema::hasTable('product_collection_items')) {
            Schema::create('product_collection_items', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('collection_id')->constrained('product_collections')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['collection_id', 'product_id'], 'product_collection_item_unique');
                $table->index(['collection_id', 'sort_order'], 'product_collection_items_sort_idx');
            });
        }

        if (! Schema::hasTable('home_sections')) {
            Schema::create('home_sections', function (Blueprint $table): void {
                $table->engine = 'InnoDB';
                $table->id();
                $table->string('type', 50);
                $table->string('title_ar');
                $table->string('title_en')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'home_sections_active_sort_idx');
                $table->index('type', 'home_sections_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('product_collection_items');
        Schema::dropIfExists('product_collections');

        if (
            Schema::hasTable('business_notifications')
            && Schema::hasColumn('business_notifications', 'read_at')
        ) {
            Schema::table('business_notifications', function (Blueprint $table): void {
                $table->dropIndex('biz_notif_recipient_read_idx');
                $table->dropColumn('read_at');
            });
        }

        Schema::dropIfExists('customer_search_history');
        Schema::dropIfExists('customer_recently_viewed_products');
    }
};
