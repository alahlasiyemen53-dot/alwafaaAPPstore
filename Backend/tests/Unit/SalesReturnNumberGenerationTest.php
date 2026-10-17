<?php

namespace Tests\Unit;

use App\Actions\GenerateReturnNumberAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SalesReturnNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_formatted_return_number(): void
    {
        $action = app(GenerateReturnNumberAction::class);
        $number = $action->execute();

        $this->assertSame('RET-000001', $number);
    }

    public function test_increments_consecutively_and_avoids_collision(): void
    {
        $currId = DB::table('currencies')->insertGetId([
            'code' => 'YER',
            'name_ar' => 'ريال',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $whId = DB::table('warehouses')->insertGetId([
            'name_ar' => 'مستودع',
            'code' => 'WH-1',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $custId = DB::table('customers')->insertGetId([
            'name' => 'عميل',
            'phone' => '777000111',
            'normalized_phone' => '+967777000111',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert dummy records to test numbering
        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'ORD-999999',
            'customer_id' => $custId,
            'warehouse_id' => $whId,
            'currency_id' => $currId,
            'status' => 'draft',
            'customer_name_snapshot' => 'Test',
            'customer_phone_snapshot' => '123',
            'subtotal' => 10,
            'discount_amount' => 0,
            'total' => 10,
            'exchange_rate_snapshot' => 1,
            'base_currency_total' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_returns')->insert([
            'id' => 5,
            'return_number' => 'RET-000006',
            'order_id' => $orderId,
            'warehouse_id' => $whId,
            'status' => 'draft',
            'reason_code' => 'customer_changed_mind',
            'currency_id' => $currId,
            'exchange_rate_snapshot' => 1,
            'subtotal' => 10,
            'total' => 10,
            'base_currency_total' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $action = app(GenerateReturnNumberAction::class);
        $number = $action->execute();

        // Since id=5 exists and RET-000006 is already used, next candidate is RET-000006, detects collision and increments to RET-000007
        $this->assertSame('RET-000007', $number);
    }
}
