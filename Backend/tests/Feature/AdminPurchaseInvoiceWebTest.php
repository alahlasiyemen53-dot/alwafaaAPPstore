<?php

namespace Tests\Feature;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPurchaseInvoiceWebTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Currency $currency;

    private Unit $unit;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->supplier = Supplier::create([
            'code' => 'SUP-TEST-INV',
            'name' => 'شركة توريد الاختبارات',
            'phone' => '777888999',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-TEST',
            'name_ar' => 'مستودع الاختبار',
            'is_active' => true,
        ]);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->unit = Unit::create([
            'code' => 'box',
            'name_ar' => 'علبة',
            'name_en' => 'Box',
            'decimal_places' => 0,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name_ar' => 'براند اختبار', 'slug' => 'test-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم اختبار', 'slug' => 'test-cat', 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج مشتريات تجريبي',
            'slug' => 'purchase-test-prod',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-TEST-001',
            'barcode' => '123456789012',
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_purchases(): void
    {
        $this->get('/admin/purchases')->assertRedirect('/admin/login');
    }

    public function test_user_without_purchases_permissions_cannot_access(): void
    {
        $user = $this->userWithRole('customer_support');
        $invoice = $this->createTestInvoice();

        $this->actingAs($user)->get('/admin/purchases')->assertForbidden();
        $this->actingAs($user)->get('/admin/purchases/create')->assertForbidden();
        $this->actingAs($user)->post('/admin/purchases', [])->assertForbidden();
        $this->actingAs($user)->get("/admin/purchases/{$invoice->id}")->assertForbidden();
        $this->actingAs($user)->get("/admin/purchases/{$invoice->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/admin/purchases/{$invoice->id}", [])->assertForbidden();
        $this->actingAs($user)->post("/admin/purchases/{$invoice->id}/post")->assertForbidden();
        $this->actingAs($user)->delete("/admin/purchases/{$invoice->id}")->assertForbidden();
    }

    public function test_authorized_user_can_create_draft_purchase_invoice(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        // Access create page
        $this->get('/admin/purchases/create')->assertOk()->assertSee('فاتورة شراء جديدة');

        $payload = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'invoice_date' => now()->toDateString(),
            'supplier_invoice_number' => 'SUPP-INV-99',
            'exchange_rate' => '1.00000000',
            'discount_amount' => '500',
            'other_costs' => '200',
            'notes' => 'تجربة فاتورة جديدة',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => '10',
                    'unit_cost' => '1000',
                    'discount_amount' => '100',
                ],
            ],
        ];

        $response = $this->post('/admin/purchases', $payload);
        $response->assertRedirect();

        $invoice = PurchaseInvoice::where('supplier_invoice_number', 'SUPP-INV-99')->firstOrFail();
        self::assertSame(PurchaseInvoiceStatus::Draft, $invoice->status);
        // subtotal = 10 * 1000 = 10000
        // discount_amount = line discount (100) + global discount (500) = 600
        // other_costs = 200
        // total = 10000 - 600 + 200 = 9600
        self::assertEquals(10000.00, $invoice->subtotal);
        self::assertEquals(600.00, $invoice->discount_amount);
        self::assertEquals(200.00, $invoice->other_costs);
        self::assertEquals(9600.00, $invoice->total);

        // Ensure no ledger entry is created yet in draft status
        $this->assertDatabaseMissing('supplier_ledger_entries', [
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        // Details page
        $this->get("/admin/purchases/{$invoice->id}")->assertOk()->assertSee($invoice->invoice_number);
    }

    public function test_posting_purchase_invoice_creates_atomic_supplier_ledger_entry(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();
        self::assertSame(PurchaseInvoiceStatus::Draft, $invoice->status);

        $response = $this->post("/admin/purchases/{$invoice->id}/post");
        $response->assertRedirect("/admin/purchases/{$invoice->id}");

        $invoice->refresh();
        self::assertSame(PurchaseInvoiceStatus::Posted, $invoice->status);
        self::assertNotNull($invoice->posted_at);
        self::assertEquals($admin->id, $invoice->posted_by);

        // Verify ledger entry
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $this->supplier->id,
            'currency_id' => $this->currency->id,
            'entry_type' => 'PURCHASE_INVOICE',
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoice->id,
            'direction' => 'credit',
            'amount' => $invoice->total,
        ]);

        // Attempting to post again should be gracefully handled or error
        $secondPost = $this->post("/admin/purchases/{$invoice->id}/post");
        $secondPost->assertRedirect("/admin/purchases/{$invoice->id}");
    }

    public function test_editing_posted_invoice_updates_supplier_ledger_entry_atomically(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();
        $this->post("/admin/purchases/{$invoice->id}/post");
        $invoice->refresh();
        self::assertSame(PurchaseInvoiceStatus::Posted, $invoice->status);

        $initialTotal = $invoice->total;

        // Verify ledger has initial total
        $ledger = SupplierLedgerEntry::where('reference_type', PurchaseInvoice::class)
            ->where('reference_id', $invoice->id)
            ->firstOrFail();
        self::assertEquals($initialTotal, $ledger->amount);

        // Now update the invoice (increase quantity from 5 to 10)
        $updatePayload = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'invoice_date' => now()->toDateString(),
            'supplier_invoice_number' => 'UPDATED-INV-01',
            'exchange_rate' => '1.00000000',
            'discount_amount' => '0',
            'other_costs' => '0',
            'notes' => 'تعديل الفاتورة بعد الترحيل',
            'items' => [
                [
                    'product_variant_id' => $this->variant->id,
                    'unit_id' => $this->unit->id,
                    'quantity' => '10',
                    'unit_cost' => '2000',
                    'discount_amount' => '0',
                ],
            ],
        ];

        $response = $this->put("/admin/purchases/{$invoice->id}", $updatePayload);
        $response->assertRedirect("/admin/purchases/{$invoice->id}");

        $invoice->refresh();
        // new total: 10 * 2000 = 20000
        self::assertEquals(20000.00, $invoice->total);

        // Verify ledger entry amount was updated in lockstep
        $ledger->refresh();
        self::assertEquals(20000.00, $ledger->amount);
        self::assertEquals(20000.00, $ledger->base_currency_amount);
    }

    public function test_deleting_posted_invoice_removes_ledger_entry_and_invoice_atomically(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();
        $this->post("/admin/purchases/{$invoice->id}/post");
        $invoice->refresh();

        $invoiceId = $invoice->id;

        // Ensure ledger exists
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoiceId,
        ]);

        // Delete invoice
        $response = $this->delete("/admin/purchases/{$invoice->id}");
        $response->assertRedirect('/admin/purchases');

        // Invoice must be deleted
        $this->assertDatabaseMissing('purchase_invoices', ['id' => $invoiceId]);

        // Items must be deleted
        $this->assertDatabaseMissing('purchase_invoice_items', ['purchase_invoice_id' => $invoiceId]);

        // Supplier ledger entry must be deleted atomically
        $this->assertDatabaseMissing('supplier_ledger_entries', [
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoiceId,
        ]);
    }

    private function createTestInvoice(): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'PINV-TEST-'.uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'invoice_date' => now(),
            'status' => PurchaseInvoiceStatus::Draft,
            'exchange_rate' => 1.00000000,
            'subtotal' => 10000.00,
            'discount_amount' => 0.00,
            'other_costs' => 0.00,
            'total' => 10000.00,
            'created_by' => auth()->id(),
        ]);

        $invoice->items()->create([
            'product_variant_id' => $this->variant->id,
            'unit_id' => $this->unit->id,
            'quantity' => 5,
            'base_quantity' => 5,
            'free_quantity' => 0,
            'unit_cost' => 2000.00,
            'line_subtotal' => 10000.00,
            'discount_amount' => 0.00,
            'line_total' => 10000.00,
        ]);

        return $invoice;
    }

    public function test_show_purchase_invoice_page_renders_successfully_with_variant_attributes(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();

        $response = $this->get("/admin/purchases/{$invoice->id}");
        $response->assertOk();
        $response->assertSee($invoice->invoice_number);
        $response->assertSee($invoice->invoice_date->format('d-m-Y'));
    }

    public function test_purchases_index_renders_velzon_date_inputs_and_standard_date_format(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();

        $response = $this->get('/admin/purchases');
        $response->assertOk();
        $response->assertSee('data-provider="flatpickr"', false);
        $response->assertSee('data-date-format="d-m-Y"', false);
        $response->assertSee('name="date_from"', false);
        $response->assertSee('name="date_to"', false);
        $response->assertSee($invoice->invoice_date->format('d-m-Y'));
    }

    public function test_purchases_date_filtering_supports_iso_and_day_month_year_formats(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);

        $invoice = $this->createTestInvoice();

        // ISO format (Y-m-d)
        $isoFrom = now()->subDay()->format('Y-m-d');
        $isoTo = now()->addDay()->format('Y-m-d');
        $this->get("/admin/purchases?date_from={$isoFrom}&date_to={$isoTo}")
            ->assertOk()
            ->assertSee($invoice->invoice_number);

        // Day-Month-Year format (d-m-Y)
        $dmyFrom = now()->subDay()->format('d-m-Y');
        $dmyTo = now()->addDay()->format('d-m-Y');
        $this->get("/admin/purchases?date_from={$dmyFrom}&date_to={$dmyTo}")
            ->assertOk()
            ->assertSee($invoice->invoice_number);

        // Out of range filter should not include this invoice
        $pastFrom = now()->subYears(3)->format('d-m-Y');
        $pastTo = now()->subYears(2)->format('d-m-Y');
        $this->get("/admin/purchases?date_from={$pastFrom}&date_to={$pastTo}")
            ->assertOk()
            ->assertDontSee($invoice->invoice_number);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
