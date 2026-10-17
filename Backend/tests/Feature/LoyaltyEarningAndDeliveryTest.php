<?php

namespace Tests\Feature;

use App\Actions\ConfirmCustomerDeliveryAction;
use App\Actions\MarkDeliveryDeliveredAction;
use App\Enums\DeliveryStatus;
use App\Enums\LoyaltyAccountStatus;
use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoyaltyEarningAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Currency $currency;

    private Warehouse $warehouse;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'مسؤول النظام',
            'email' => 'admin@lamma.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'أحمد علي',
            'phone' => '770112233',
            'normalized_phone' => '770112233',
            'is_active' => true,
        ]);

        $this->currency = Currency::create([
            'name_ar' => 'ريال يمني',
            'name_en' => 'YER',
            'code' => 'YER',
            'symbol' => 'ر.ي',
            'is_base' => true,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->driver = Driver::create([
            'name' => 'السائق الأول',
            'code' => 'DRV-001',
            'phone' => '770998877',
            'is_active' => true,
        ]);

        Setting::updateOrCreate(['key' => 'loyalty.spend_per_point'], ['value' => '1000', 'type' => 'decimal']);
    }

    public function test_marking_delivery_delivered_earns_loyalty_points_once(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-1001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'status' => OrderStatus::AssignedToDriver,
            'subtotal' => 25000.0,
            'discount_amount' => 5000.0,
            'total' => 20000.0,
            'base_currency_total' => '20000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-1001',
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => now(),
            'cod_amount_to_collect' => 20000.0,
        ]);

        $action = app(MarkDeliveryDeliveredAction::class);
        $delivered = $action->execute($delivery, 20000.0, 'تم التسليم بنجاح', $this->admin->id);

        self::assertSame(DeliveryStatus::Delivered, $delivered->status);
        self::assertSame(OrderStatus::AwaitingCustomerConfirmation, $order->fresh()->status);

        app(ConfirmCustomerDeliveryAction::class)->execute($order->fresh(), (int) $this->customer->id);
        self::assertSame(OrderStatus::Delivered, $order->fresh()->status);

        // Assert Loyalty Account created & Entry added
        $account = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->first();
        self::assertNotNull($account);
        self::assertSame(LoyaltyAccountStatus::Active, $account->status);

        $entry = CustomerLoyaltyEntry::where('customer_loyalty_account_id', $account->id)->first();
        self::assertNotNull($entry);
        self::assertSame(20, $entry->points); // 20000 / 1000 = 20
        self::assertSame(LoyaltyEntryType::Earn, $entry->type);
        self::assertSame('Order', $entry->source_type);
        self::assertSame($order->id, $entry->source_id);
        self::assertSame('DELIVERY_DELIVERED', $entry->source_event);

        // Assert Audit recorded
        self::assertTrue(AuditLog::where('action', 'admin.loyalty.points_earned')->exists());
    }

    public function test_repeated_delivery_action_is_idempotent_and_does_not_duplicate_points(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-1002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'status' => OrderStatus::AssignedToDriver,
            'total' => 15000.0,
            'base_currency_total' => '15000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-1002',
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => now(),
            'cod_amount_to_collect' => 15000.0,
        ]);

        $action = app(MarkDeliveryDeliveredAction::class);
        $action->execute($delivery, 15000.0, 'تسليم 1', $this->admin->id);

        // Execute second time on already delivered
        $action->execute($delivery, 15000.0, 'تسليم 2', $this->admin->id);

        self::assertNull(CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->first());

        $confirm = app(ConfirmCustomerDeliveryAction::class);
        $confirm->execute($order->fresh(), (int) $this->customer->id);
        $confirm->execute($order->fresh(), (int) $this->customer->id);

        $account = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->first();
        self::assertNotNull($account);
        self::assertSame(1, $account->entries()->count());
        self::assertSame(15, (int) $account->entries()->sum('points'));
    }

    public function test_suspended_loyalty_account_does_not_earn_points(): void
    {
        CustomerLoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'status' => LoyaltyAccountStatus::Suspended,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-1003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'status' => OrderStatus::AssignedToDriver,
            'total' => 10000.0,
            'base_currency_total' => '10000.0000',
            'exchange_rate_snapshot' => '1.00000000',
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-1003',
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => DeliveryStatus::Assigned,
            'assigned_at' => now(),
            'cod_amount_to_collect' => 10000.0,
        ]);

        $action = app(MarkDeliveryDeliveredAction::class);
        $action->execute($delivery, 10000.0, 'تسليم معلق', $this->admin->id);
        app(ConfirmCustomerDeliveryAction::class)->execute($order->fresh(), (int) $this->customer->id);

        $account = CustomerLoyaltyAccount::where('customer_id', $this->customer->id)->first();
        self::assertSame(0, $account->entries()->count());
    }
}
