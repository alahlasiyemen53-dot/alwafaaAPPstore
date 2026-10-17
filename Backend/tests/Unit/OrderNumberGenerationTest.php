<?php

namespace Tests\Unit;

use App\Actions\GenerateOrderNumberAction;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_and_formatted_order_number(): void
    {
        $action = new GenerateOrderNumberAction;

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'عميل',
            'phone' => '770000000',
            'normalized_phone' => '+967770000000',
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create([
            'name_ar' => 'مستودع',
            'code' => 'WH-NUM',
            'is_active' => true,
        ]);

        $first = $action->execute();
        $this->assertSame('ORD-000001', $first);

        Order::query()->create([
            'order_number' => $first,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'currency_id' => $currency->id,
            'customer_name_snapshot' => 'Dummy',
            'customer_phone_snapshot' => '123',
            'subtotal' => 0,
            'total' => 0,
            'base_currency_total' => 0,
        ]);

        $second = $action->execute();
        $this->assertSame('ORD-000002', $second);
    }
}
