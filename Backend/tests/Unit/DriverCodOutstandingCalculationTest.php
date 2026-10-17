<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\CalculateDriverCodOutstandingAction;
use App\Enums\DeliveryStatus;
use App\Enums\DriverCodSettlementStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\DriverCodSettlementItem;
use App\Models\Order;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DriverCodOutstandingCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Driver $driver;

    private Currency $currency;

    private Customer $customer;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->currency = Currency::where('code', 'YER')->first() ?? Currency::first();
        $this->driver = Driver::create([
            'name' => 'مندوب التوصيل فهد',
            'driver_code' => 'DRV-001',
            'phone' => '777000111',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '777222333',
            'normalized_phone' => '777222333',
        ]);

        $this->warehouse = Warehouse::first() ?? Warehouse::create([
            'code' => 'WH-01',
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'is_active' => true,
        ]);
    }

    private function createOrderWithDelivery(float $codAmount, DeliveryStatus $status = DeliveryStatus::Delivered): Delivery
    {
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'customer_phone_snapshot' => $this->customer->phone,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'status' => 'delivered',
            'subtotal' => $codAmount,
            'discount_total' => 0,
            'tax_total' => 0,
            'delivery_fee' => 0,
            'total' => $codAmount,
        ]);

        return Delivery::create([
            'delivery_number' => 'DEL-'.uniqid(),
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
            'assigned_at' => now()->subHours(2),
            'delivered_at' => $status === DeliveryStatus::Delivered ? now()->subHour() : null,
            'cod_amount_to_collect' => $codAmount,
            'cod_collected_amount' => $status === DeliveryStatus::Delivered ? $codAmount : null,
            'cod_currency_id' => $this->currency->id,
        ]);
    }

    public function test_delivery_eligibility_for_cod_settlement(): void
    {
        $deliveredWithCash = $this->createOrderWithDelivery(30000, DeliveryStatus::Delivered);
        $this->assertTrue($deliveredWithCash->isEligibleForCodSettlement());
        $this->assertEquals(30000, $deliveredWithCash->outstanding_cod_amount);

        $deliveredZeroCash = $this->createOrderWithDelivery(0, DeliveryStatus::Delivered);
        $this->assertFalse($deliveredZeroCash->isEligibleForCodSettlement());
        $this->assertEquals(0, $deliveredZeroCash->outstanding_cod_amount);

        $assignedDelivery = $this->createOrderWithDelivery(20000, DeliveryStatus::Assigned);
        $this->assertFalse($assignedDelivery->isEligibleForCodSettlement());
        $this->assertEquals(0, $assignedDelivery->outstanding_cod_amount);
    }

    public function test_driver_outstanding_calculated_accurately_with_partial_settlement(): void
    {
        $action = app(CalculateDriverCodOutstandingAction::class);

        // Delivery 1: 50,000
        $d1 = $this->createOrderWithDelivery(50000);
        // Delivery 2: 30,000
        $d2 = $this->createOrderWithDelivery(30000);

        // Before any settlement: 80,000 collected, 0 settled, 80,000 outstanding
        $calc1 = $action->execute($this->driver->id, $this->currency->id);
        $this->assertEquals(80000, $calc1['total_collected']);
        $this->assertEquals(0, $calc1['total_settled']);
        $this->assertEquals(80000, $calc1['total_outstanding']);
        $this->assertEquals(2, $calc1['eligible_count']);

        // Create a POSTED partial settlement: 20,000 on d1, 10,000 on d2 (Total 30,000)
        $settlement = DriverCodSettlement::create([
            'settlement_number' => 'COD-SET-001',
            'driver_id' => $this->driver->id,
            'currency_id' => $this->currency->id,
            'status' => DriverCodSettlementStatus::Posted,
            'total_expected' => 80000,
            'total_settled' => 30000,
            'posted_at' => now(),
        ]);

        DriverCodSettlementItem::create([
            'settlement_id' => $settlement->id,
            'delivery_id' => $d1->id,
            'order_id' => $d1->order_id,
            'collected_amount' => 50000,
            'previously_settled_amount' => 0,
            'settlement_amount' => 20000,
            'currency_id' => $this->currency->id,
        ]);

        DriverCodSettlementItem::create([
            'settlement_id' => $settlement->id,
            'delivery_id' => $d2->id,
            'order_id' => $d2->order_id,
            'collected_amount' => 30000,
            'previously_settled_amount' => 0,
            'settlement_amount' => 10000,
            'currency_id' => $this->currency->id,
        ]);

        // After POSTED settlement: 80,000 collected, 30,000 settled, 50,000 outstanding
        $calc2 = $action->execute($this->driver->id, $this->currency->id);
        $this->assertEquals(80000, $calc2['total_collected']);
        $this->assertEquals(30000, $calc2['total_settled']);
        $this->assertEquals(50000, $calc2['total_outstanding']);

        // Check individual delivery remaining amounts
        $this->assertEquals(30000, $d1->fresh()->outstanding_cod_amount);
        $this->assertEquals(20000, $d2->fresh()->outstanding_cod_amount);

        // Draft settlement should NOT reduce outstanding
        $draft = DriverCodSettlement::create([
            'settlement_number' => 'COD-SET-002',
            'driver_id' => $this->driver->id,
            'currency_id' => $this->currency->id,
            'status' => DriverCodSettlementStatus::Draft,
            'total_expected' => 50000,
            'total_settled' => 50000,
        ]);

        DriverCodSettlementItem::create([
            'settlement_id' => $draft->id,
            'delivery_id' => $d1->id,
            'order_id' => $d1->order_id,
            'collected_amount' => 50000,
            'previously_settled_amount' => 20000,
            'settlement_amount' => 30000,
            'currency_id' => $this->currency->id,
        ]);

        $calc3 = $action->execute($this->driver->id, $this->currency->id);
        $this->assertEquals(50000, $calc3['total_outstanding'], 'Draft settlement must not reduce driver outstanding');

        // Reversed settlement should restore outstanding
        $settlement->update(['status' => DriverCodSettlementStatus::Reversed]);
        $calc4 = $action->execute($this->driver->id, $this->currency->id);
        $this->assertEquals(80000, $calc4['total_outstanding'], 'Reversed settlement must restore driver outstanding');
    }
}
