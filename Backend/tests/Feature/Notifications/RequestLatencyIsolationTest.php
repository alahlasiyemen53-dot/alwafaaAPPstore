<?php

namespace Tests\Feature\Notifications;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\OrderStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PushDevice;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RequestLatencyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_slow_sender_never_blocks_request_and_is_only_invoked_by_queue_worker(): void
    {
        Queue::fake();

        // Configure a blocking/failing sender fake that tracks calls
        $slowSender = new class implements PushNotificationSender
        {
            public int $callCount = 0;

            public function send(PushNotificationMessageData $message, string $deviceToken): PushSendResultData
            {
                $this->callCount++;

                return new PushSendResultData(true, 'fcm-provider-ref');
            }

            public function sendToTopic(PushNotificationMessageData $message, string $topic): PushSendResultData
            {
                return new PushSendResultData(true, 'fcm-topic-ref');
            }
        };

        $this->app->instance(PushNotificationSender::class, $slowSender);

        // Setup test data
        $currency = Currency::create(['code' => 'YER', 'name_ar' => 'ريال يمني', 'name_en' => 'Yemeni Rial', 'symbol' => 'YR', 'exchange_rate_to_base' => 1.0, 'is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['name' => 'Salem Moh', 'phone' => '777123456', 'normalized_phone' => '777123456', 'is_active' => true]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المنزل',
            'contact_name' => $customer->name,
            'contact_phone' => $customer->phone,
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);
        $warehouse = Warehouse::create(['code' => 'WH-01', 'name_ar' => 'المستودع الرئيسي', 'name_en' => 'Main WH', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-1', 'is_active' => true]);
        $unit = Unit::create(['code' => 'PCS', 'name_ar' => 'قطعة', 'decimal_places' => 0, 'is_active' => true]);
        $product = Product::create(['name_ar' => 'منتج 1', 'slug' => 'p1', 'category_id' => $category->id, 'status' => 'active']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-001',
            'price' => 100,
            'currency_id' => $currency->id,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_active' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => 100.0,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $createData = new CreateOrderData(
            customerId: $customer->id,
            customerAddressId: $address->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [new OrderItemData(productVariantId: $variant->id, quantity: 1.0)],
        );
        $user = User::factory()->create(['is_active' => true]);
        $order = app(CreateOrderAction::class)->execute($createData, $user->id);

        // 1. Execute the business action
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $user->id);

        // Invariant check: Sender had ZERO calls during business execution
        self::assertSame(OrderStatus::Confirmed, $confirmedOrder->status);
        self::assertSame(0, $slowSender->callCount, 'Sender contract must NOT be invoked synchronously during order confirmation.');

        // Assert job was pushed to queue after commit
        Queue::assertPushed(SendPushNotificationJob::class, 1);

        $notification = BusinessNotification::query()->where('business_reference', $confirmedOrder->order_number)->first();
        self::assertNotNull($notification);

        // 2. Now register a device and run worker explicitly
        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
            'locale' => 'ar',
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->status = PushDeviceStatus::Active;
        $device->token = 'tok-abc';
        $device->token_hash = hash('sha256', 'tok-abc');
        $device->save();

        // Worker execution
        $job = new SendPushNotificationJob($notification->id);
        $this->app->call([$job, 'handle']);

        // Invariant check: Sender was called ONLY by the worker
        self::assertSame(1, $slowSender->callCount, 'Sender contract should be invoked only by queue worker.');
    }
}
