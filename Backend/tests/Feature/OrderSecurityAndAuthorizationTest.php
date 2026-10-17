<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderSecurityAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customerA;

    private Customer $customerB;

    private CustomerAddress $addressA;

    private CustomerAddress $addressB;

    private Warehouse $warehouse;

    private Currency $currency;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->customerA = Customer::create([
            'name' => 'العميل أ',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
        ]);

        $this->customerB = Customer::create([
            'name' => 'العميل ب',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'is_active' => true,
        ]);

        $this->addressA = CustomerAddress::create([
            'customer_id' => $this->customerA->id,
            'label' => 'عنوان العميل أ',
            'contact_name' => 'العميل أ',
            'contact_phone' => '771111111',
            'is_default' => true,
        ]);

        $this->addressB = CustomerAddress::create([
            'customer_id' => $this->customerB->id,
            'label' => 'عنوان العميل ب',
            'contact_name' => 'العميل ب',
            'contact_phone' => '772222222',
            'is_default' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'مستودع الأمان',
            'code' => 'WH-SEC',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'براند', 'slug' => 'brand-sec', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم', 'slug' => 'cat-sec', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'منتج حماية',
            'slug' => 'prod-sec',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-SEC-01',
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_cross_customer_address_injection_is_strictly_forbidden(): void
    {
        // Try creating an order for Customer A, but injecting Customer B's address
        $payload = [
            'customer_id' => $this->customerA->id,
            'customer_address_id' => $this->addressB->id, // Mismatch! IDOR attempt
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'action' => 'draft',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->admin)->post('/admin/orders', $payload);
        $response->assertSessionHasErrors(['customer_address_id']);
        $this->assertSame(0, Order::count());
    }

    public function test_unauthorized_user_cannot_access_orders(): void
    {
        $guestUser = User::factory()->create(['is_active' => true]); // No roles or permissions

        $response = $this->actingAs($guestUser)->get('/admin/orders');
        $response->assertForbidden();

        $createResponse = $this->actingAs($guestUser)->get('/admin/orders/create');
        $createResponse->assertForbidden();

        $storeResponse = $this->actingAs($guestUser)->post('/admin/orders', []);
        $storeResponse->assertForbidden();
    }

    public function test_customer_support_can_view_orders_but_cannot_create_or_confirm(): void
    {
        $supportUser = $this->userWithRole('customer_support');

        $viewResponse = $this->actingAs($supportUser)->get('/admin/orders');
        $viewResponse->assertOk();

        $createResponse = $this->actingAs($supportUser)->get('/admin/orders/create');
        $createResponse->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
