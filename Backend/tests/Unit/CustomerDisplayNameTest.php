<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Support\CustomerDisplayName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_privacy_safe_masked_display_name(): void
    {
        $customer = Customer::query()->create([
            'name' => 'أحمد محمد',
            'phone' => '770000100',
            'normalized_phone' => '+967770000100',
            'is_active' => true,
        ]);

        $this->assertSame('أح****مد مح****مد', CustomerDisplayName::from($customer));
    }

    public function test_single_name_is_masked(): void
    {
        $customer = Customer::query()->create([
            'name' => 'سارة',
            'phone' => '770000101',
            'normalized_phone' => '+967770000101',
            'is_active' => true,
        ]);

        $this->assertSame('سا****رة', CustomerDisplayName::from($customer));
    }

    public function test_baraa_name_is_masked(): void
    {
        $customer = Customer::query()->create([
            'name' => 'براء',
            'phone' => '770000102',
            'normalized_phone' => '+967770000102',
            'is_active' => true,
        ]);

        $this->assertSame('بر****اء', CustomerDisplayName::from($customer));
    }

    public function test_null_customer_returns_fallback(): void
    {
        $this->assertSame('عميل', CustomerDisplayName::from(null));
    }
}
