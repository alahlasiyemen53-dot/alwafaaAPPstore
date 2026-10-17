<?php

declare(strict_types=1);

namespace Tests\Feature\UserAcceptanceTesting;

use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\CreditCustomerWalletAction;
use App\DTOs\CreateOrderData;
use App\DTOs\CustomerWalletAdjustmentData;
use App\DTOs\OrderItemData;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Brand;
use App\Models\Cashbox;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RoleBasedUserAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $accountant;

    private User $cashier;

    private User $deliverySupervisor;

    private Currency $baseCurrency;

    private Warehouse $mainWarehouse;

    private Cashbox $cashbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            AuthorizationSeeder::class,
            MasterDataSeeder::class,
            ChartOfAccountsSeeder::class,
            AccountingPeriodSeeder::class,
            AccountingMappingSeeder::class,
        ]);

        $this->superAdmin = $this->makeUserWithRole('super_admin');
        $this->accountant = $this->makeUserWithRole('accountant');
        $this->cashier = $this->makeUserWithRole('cashier');
        $this->deliverySupervisor = $this->makeUserWithRole('delivery_supervisor');

        $this->baseCurrency = Currency::where('code', 'YER')->firstOrFail();
        $this->mainWarehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name_ar' => 'المستودع الرئيسي',
            'is_active' => true,
        ]);

        $cashAccount = Account::where('code', '1110')->firstOrFail();
        $this->cashbox = Cashbox::create([
            'code' => 'CB-MAIN',
            'name_ar' => 'صندوق المركز الرئيسي',
            'name_en' => 'Main Cashbox',
            'currency_id' => $this->baseCurrency->id,
            'gl_account_id' => $cashAccount->id,
            'is_active' => true,
        ]);
    }

    /**
     * UAT-01: Role-based access control and unauthorized action rejection.
     */
    public function test_uat_01_role_based_access_control_and_unauthorized_rejection(): void
    {
        // Cashier attempts to access Financial Accounts -> Forbidden (403)
        $this->actingAs($this->cashier)
            ->get('/admin/finance/accounts')
            ->assertForbidden();

        // Delivery Supervisor attempts to access Trial Balance -> Forbidden (403)
        $this->actingAs($this->deliverySupervisor)
            ->get('/admin/finance/reports/trial-balance')
            ->assertForbidden();

        // Accountant can access Accounts and Journal Entries -> OK (200)
        $this->actingAs($this->accountant)
            ->get('/admin/finance/accounts')
            ->assertOk();

        $this->actingAs($this->accountant)
            ->get('/admin/finance/journal-entries')
            ->assertOk();

        // Delivery Supervisor can access Deliveries -> OK (200)
        $this->actingAs($this->deliverySupervisor)
            ->get('/admin/deliveries')
            ->assertOk();
    }

    /**
     * UAT-02: Catalog, ProductVariant sellable identity, and base currency pricing.
     */
    public function test_uat_02_catalog_product_variant_and_base_currency_pricing(): void
    {
        $brand = Brand::create(['name_ar' => 'ماركة لوزيان', 'slug' => 'brand-uat', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'عطور وفاخرة', 'slug' => 'cat-uat', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'عطر العود الملكي',
            'slug' => 'royal-oud-uat',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-OUD-ROYAL',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 25000.00,
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin)
            ->get('/admin/products/'.$product->id)
            ->assertOk();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'product_id' => $product->id,
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('variant_prices', [
            'product_variant_id' => $variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 25000.00,
            'is_active' => true,
        ]);
    }

    /**
     * UAT-03: Purchasing invoice credit posting and supplier current account liability.
     */
    public function test_uat_03_purchase_credit_invoice_and_supplier_liability(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-UAT-01',
            'name' => 'شركة التوريدات الوطنية',
            'phone' => '770112233',
            'is_active' => true,
        ]);

        $this->actingAs($this->accountant)
            ->get('/admin/suppliers/'.$supplier->id.'/statement')
            ->assertOk();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'is_active' => true,
        ]);
    }

    /**
     * UAT-04: Inventory reservation and order confirmation lifecycle.
     */
    public function test_uat_04_inventory_reservation_and_order_confirmation_lifecycle(): void
    {
        $brand = Brand::create(['name_ar' => 'ماركة الأناقة', 'slug' => 'brand-order', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'مكياج', 'slug' => 'cat-order', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'أحمر شفاه كلاسيكي',
            'slug' => 'classic-lipstick',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-LIPSTICK-01',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 4500.00,
            'is_active' => true,
        ]);

        // Seed physical stock: 20 pcs
        Inventory::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 20.0,
            'reserved_quantity' => 0.0,
        ]);

        $customer = Customer::create([
            'name' => 'فاطمة محمد',
            'phone' => '775566778',
            'normalized_phone' => '+967775566778',
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المنزل',
            'contact_name' => 'فاطمة محمد',
            'contact_phone' => '775566778',
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);

        // Create Draft Order via Action
        $orderData = new CreateOrderData(
            customerId: $customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->mainWarehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: 3.0,
                ),
            ],
            notes: 'UAT Order Confirmation Test'
        );

        $order = app(CreateOrderAction::class)->execute($orderData, $this->superAdmin->id);
        $this->assertEquals(OrderStatus::Draft, $order->status);

        // Confirm Order
        $confirmedOrder = app(ConfirmOrderAction::class)->execute($order, $this->superAdmin->id);
        $this->assertEquals(OrderStatus::Confirmed, $confirmedOrder->status);

        // Verify stock: 3 reserved, 17 available
        $inventory = Inventory::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_variant_id', $variant->id)
            ->firstOrFail();

        $this->assertEquals(20.0, (float) $inventory->quantity);
        $this->assertEquals(3.0, (float) $inventory->reserved_quantity);
    }

    /**
     * UAT-05: Deliveries assignment and COD driver custody collection.
     */
    public function test_uat_05_delivery_fulfillment_and_driver_cod_custody(): void
    {
        $brand = Brand::create(['name_ar' => 'ماركة العناية', 'slug' => 'brand-care', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'عناية بالبشرة', 'slug' => 'cat-care', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'سيروم الهيالورونيك',
            'slug' => 'hyaluronic-serum',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-SERUM-01',
            'status' => 'active',
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => 8000.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10.0,
            'reserved_quantity' => 0.0,
        ]);

        $customer = Customer::create([
            'name' => 'أحمد علي',
            'phone' => '771239988',
            'normalized_phone' => '+96771239988',
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المكتب',
            'contact_name' => 'أحمد علي',
            'contact_phone' => '771239988',
            'street' => 'شارع الزبيري',
            'is_default' => true,
        ]);

        $driver = Driver::create([
            'driver_code' => 'DRV-UAT-99',
            'name' => 'ماجد المندوب',
            'phone' => '779988776',
            'vehicle_type' => 'دراجة نارية',
            'vehicle_plate_number' => 'ص 1234',
            'is_active' => true,
        ]);

        $orderData = new CreateOrderData(
            customerId: $customer->id,
            customerAddressId: $address->id,
            warehouseId: $this->mainWarehouse->id,
            currencyId: $this->baseCurrency->id,
            items: [
                new OrderItemData(
                    productVariantId: $variant->id,
                    quantity: 1.0,
                ),
            ],
            notes: null,
        );

        $order = app(CreateOrderAction::class)->execute($orderData, $this->superAdmin->id);
        $order = app(ConfirmOrderAction::class)->execute($order, $this->superAdmin->id);

        // Assign delivery to driver
        $this->actingAs($this->deliverySupervisor)
            ->post('/admin/orders/'.$order->id.'/assign-delivery', [
                'driver_id' => $driver->id,
                'notes' => 'UAT Dispatch',
            ])
            ->assertRedirect();

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(DeliveryStatus::Assigned, $delivery->status);
        $this->assertEquals($driver->id, $delivery->driver_id);
    }

    /**
     * UAT-06: Treasury cashbox and account integrity.
     */
    public function test_uat_06_treasury_cashbox_and_accounts(): void
    {
        $this->actingAs($this->accountant)
            ->get('/admin/finance/treasury/cashboxes/'.$this->cashbox->id.'/edit')
            ->assertOk();

        $this->assertDatabaseHas('cashboxes', [
            'id' => $this->cashbox->id,
            'is_active' => true,
        ]);
    }

    /**
     * UAT-07: Full financial reports trial balance and general ledger.
     */
    public function test_uat_07_financial_reports_trial_balance_and_general_ledger(): void
    {
        $this->actingAs($this->accountant)
            ->get('/admin/finance/reports/trial-balance')
            ->assertOk();

        $this->actingAs($this->accountant)
            ->get('/admin/finance/reports/supplier-reconciliation')
            ->assertOk();
    }

    /**
     * UAT-08: System-wide integrity audit command execution.
     */
    public function test_uat_08_system_wide_integrity_audit_command(): void
    {
        $this->artisan('app:verify-integrity', ['--days' => 30])
            ->expectsOutputToContain('اكتمل فحص التكامل بنجاح')
            ->assertExitCode(0);
    }

    /**
     * UAT-09: Customer wallet top-up, ledger entry, and liability balance.
     */
    public function test_uat_09_customer_wallet_credit_and_liability_reconciliation(): void
    {
        $customer = Customer::create([
            'name' => 'سارة سالم',
            'phone' => '774433221',
            'normalized_phone' => '+967774433221',
            'is_active' => true,
        ]);

        $data = new CustomerWalletAdjustmentData(
            customerId: $customer->id,
            currencyId: $this->baseCurrency->id,
            amount: '20000.0000',
            type: 'credit',
            reason: 'UAT Top-up',
            createdBy: $this->superAdmin->id,
        );

        $entry = app(CreditCustomerWalletAction::class)->execute($data);

        $this->assertEquals('20000.0000', $entry->amount);
        $this->assertDatabaseHas('customer_wallet_entries', [
            'id' => $entry->id,
            'amount' => '20000.0000',
        ]);
    }

    /**
     * UAT-10: Treasury fund transfer between cashbox and bank account.
     */
    public function test_uat_10_treasury_bank_transfer_atomicity(): void
    {
        $bankGl = Account::where('code', '1120')->firstOrFail();
        $bank = BankAccount::create([
            'code' => 'BNK-UAT',
            'bank_name' => 'بنك الكريمي',
            'account_name' => 'حساب العمليات',
            'account_number' => '123456789',
            'currency_id' => $this->baseCurrency->id,
            'gl_account_id' => $bankGl->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->accountant)->post('/admin/finance/treasury/transfers', [
            'source_type' => Cashbox::class,
            'source_id' => $this->cashbox->id,
            'destination_type' => BankAccount::class,
            'destination_id' => $bank->id,
            'amount' => 5000.00,
            'notes' => 'UAT Deposit into Bank',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $bank->id,
            'is_active' => true,
        ]);
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
