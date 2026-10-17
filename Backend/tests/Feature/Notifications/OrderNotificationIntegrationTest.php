<?php

namespace Tests\Feature\Notifications;

use App\Actions\AssignOrderToDriverAction;
use App\Actions\CancelOrderAction;
use App\Actions\ConfirmCustomerDeliveryAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\MarkDeliveryDeliveredAction;
use App\Contracts\Notifications\PushNotificationSender;
use App\DTOs\AssignOrderToDriverData;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Models\BusinessNotification;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\Notifications\FakePushNotificationSender;
use Tests\TestCase;

final class OrderNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $currency;

    private Unit $unit;

    private ProductVariant $variant;

    private Driver $driver;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PushNotificationSender::class, new FakePushNotificationSender);
        Queue::fake();

        $this->user = User::factory()->create(['is_active' => true]);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'YR',
            'exchange_rate_to_base' => 1.0,
            'is_default' => true,
            'is_active' => true,
        ]);
        $this->customer = Customer::create([
            'name' => 'Customer One',
            'phone' => '777888999',
            'normalized_phone' => '+967777888999',
            'is_active' => true,
        ]);
        $this->address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'label' => 'المنزل',
            'contact_name' => $this->customer->name,
            'contact_phone' => $this->customer->phone,
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);
        $this->warehouse = Warehouse::create([
            'code' => 'WH-01',
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'WH 1',
            'is_active' => true,
        ]);
        $this->unit = Unit::create(['code' => 'PCS', 'name_ar' => 'قطعة', 'decimal_places' => 0, 'is_active' => true]);

        $category = Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-notif', 'is_active' => true]);
        $product = Product::create(['name_ar' => 'منتج تجريبي', 'slug' => 'prod-notif', 'category_id' => $category->id, 'status' => 'active']);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-1',
            'price' => 50,
            'currency_id' => $this->currency->id,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
            'is_active' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 50.0,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->driver = Driver::create([
            'driver_code' => 'DRV-001',
            'name' => 'Driver One',
            'phone' => '777111222',
            'is_active' => true,
        ]);
    }

    public function test_confirming_order_schedules_order_confirmed_notification(): void
    {
        $order = $this->createDraftOrder();

        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->user->id);

        $notification = BusinessNotification::query()
            ->where('idempotency_key', "order:{$confirmed->id}:order_confirmed:v1")
            ->first();

        self::assertNotNull($notification);
        self::assertSame(NotificationType::OrderConfirmed, $notification->type);
        self::assertSame($confirmed->order_number, $notification->business_reference);
        self::assertSame($this->customer->id, $notification->recipient_id);
    }

    public function test_assigning_to_driver_schedules_order_assigned_notification(): void
    {
        $order = $this->createDraftOrder();
        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->user->id);

        $delivery = app(AssignOrderToDriverAction::class)->execute(
            $confirmed->fresh(),
            new AssignOrderToDriverData(driverId: $this->driver->id, notes: 'Assign notes'),
            $this->user->id
        );

        $notification = BusinessNotification::query()
            ->where('idempotency_key', "order:{$confirmed->id}:order_assigned_to_driver:v1")
            ->first();

        self::assertNotNull($notification);
        self::assertSame(NotificationType::OrderAssignedToDriver, $notification->type);
        self::assertSame($confirmed->order_number, $notification->business_reference);
        self::assertSame($this->customer->id, $notification->recipient_id);
    }

    public function test_delivering_order_schedules_order_delivered_notification(): void
    {
        $order = $this->createDraftOrder();
        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->user->id);

        $delivery = app(AssignOrderToDriverAction::class)->execute(
            $confirmed->fresh(),
            new AssignOrderToDriverData(driverId: $this->driver->id),
            $this->user->id
        );

        app(MarkDeliveryDeliveredAction::class)->execute($delivery, (float) $confirmed->total, 'Delivered fine', $this->user->id);

        $awaitingNotification = BusinessNotification::query()
            ->where('idempotency_key', "order:{$confirmed->id}:awaiting_customer_confirmation:v1")
            ->first();

        self::assertNotNull($awaitingNotification);
        self::assertSame(NotificationType::OrderAwaitingCustomerConfirmation, $awaitingNotification->type);
        self::assertSame($confirmed->order_number, $awaitingNotification->business_reference);
        self::assertSame(OrderStatus::AwaitingCustomerConfirmation, $confirmed->fresh()->status);

        app(ConfirmCustomerDeliveryAction::class)->execute($confirmed->fresh(), (int) $this->customer->id);

        $completedNotification = BusinessNotification::query()
            ->where('idempotency_key', "order:{$confirmed->id}:order_completed:v1")
            ->first();

        self::assertNotNull($completedNotification);
        self::assertSame(NotificationType::OrderDelivered, $completedNotification->type);
        self::assertSame(OrderStatus::Delivered, $confirmed->fresh()->status);
    }

    public function test_cancelling_order_schedules_order_cancelled_notification(): void
    {
        $order = $this->createDraftOrder();
        $confirmed = app(ConfirmOrderAction::class)->execute($order, $this->user->id);

        app(CancelOrderAction::class)->execute(
            $confirmed->fresh(),
            new CancelOrderData(cancellationReason: 'Customer request'),
            $this->user->id
        );

        $notification = BusinessNotification::query()
            ->where('idempotency_key', "order:{$confirmed->id}:order_cancelled:v1")
            ->first();

        self::assertNotNull($notification);
        self::assertSame(NotificationType::OrderCancelled, $notification->type);
        self::assertSame($confirmed->order_number, $notification->business_reference);
    }

    private function createDraftOrder(): Order
    {
        $data = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $this->currency->id,
            items: [
                new OrderItemData(productVariantId: $this->variant->id, quantity: 1.0),
            ],
            notes: null,
        );

        return app(CreateOrderAction::class)->execute($data, $this->user->id);
    }
}
