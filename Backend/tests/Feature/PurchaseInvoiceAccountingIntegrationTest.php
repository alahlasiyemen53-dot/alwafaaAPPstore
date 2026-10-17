<?php

namespace Tests\Feature;

use App\Actions\BuildJournalFromPurchaseInvoiceAction;
use App\Actions\PostPurchaseInvoiceAction;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Account;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SupplierLedgerReconciliationService;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\AccountingPeriodSeeder;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurchaseInvoiceAccountingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private Currency $currency;

    private Unit $unit;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AuthorizationSeeder::class);
        $this->seed(MasterDataSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingPeriodSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@lamma.test'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );
        $this->admin->syncRoles(['super_admin']);
        $this->actingAs($this->admin);

        $this->supplier = Supplier::create([
            'code' => 'SUPP-0001',
            'name' => 'شركة التوريد الذهبية',
            'phone' => '777123456',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MAIN'],
            ['name_ar' => 'المستودع الرئيسي', 'is_active' => true]
        );
        $this->currency = Currency::where('is_default', true)->firstOrFail();
        $this->unit = Unit::firstOrFail();

        $brand = Brand::create(['name_ar' => 'براند اختبار', 'slug' => 'test-brand-pi', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'قسم اختبار', 'slug' => 'test-cat-pi', 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج مشتريات تجريبي',
            'slug' => 'test-purchase-prod',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PURCHASE-01',
            'barcode' => 'BAR-PURCHASE-01',
            'unit_id' => $this->unit->id,
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_posting_purchase_invoice_creates_and_posts_financial_journal_entry(): void
    {
        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'INV-2026-0001',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'status' => PurchaseInvoiceStatus::Draft,
            'subtotal' => 2000.0,
            'tax_total' => 0.0,
            'discount_total' => 0.0,
            'total' => 2000.0,
            'invoice_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variant->id,
            'unit_id' => $this->unit->id,
            'quantity' => 10,
            'unit_price' => 200.0,
            'subtotal' => 2000.0,
            'total' => 2000.0,
        ]);

        $action = app(PostPurchaseInvoiceAction::class);
        $postedInvoice = $action->execute($invoice);

        $this->assertEquals(PurchaseInvoiceStatus::Posted, $postedInvoice->status);

        // 1. Financial Journal Entry must exist
        $entry = JournalEntry::query()
            ->where('source_type', PurchaseInvoice::class)
            ->where('source_id', $postedInvoice->id)
            ->where('source_event', 'POSTED')
            ->first();

        $this->assertNotNull($entry);
        $this->assertTrue($entry->isPosted());
        $this->assertEquals('2000.0000', (string) $entry->total_debit);
        $this->assertEquals('2000.0000', (string) $entry->total_credit);

        // 2. Debit line: Inventory/Purchases (1140)
        $inventoryAccount = Account::where('code', '1140')->firstOrFail();
        $debitLine = $entry->lines()->where('account_id', $inventoryAccount->id)->firstOrFail();
        $this->assertEquals('2000.0000', (string) $debitLine->debit);
        $this->assertEquals('0.0000', (string) $debitLine->credit);

        // 3. Credit line: Accounts Payable (2110) with party link to supplier
        $payableAccount = Account::where('code', '2110')->firstOrFail();
        $creditLine = $entry->lines()->where('account_id', $payableAccount->id)->firstOrFail();
        $this->assertEquals('0.0000', (string) $creditLine->debit);
        $this->assertEquals('2000.0000', (string) $creditLine->credit);
        $this->assertEquals(Supplier::class, $creditLine->party_type);
        $this->assertEquals($this->supplier->id, $creditLine->party_id);

        // 4. Supplier Ledger ↔ General Ledger reconciliation is 100% matched
        $reconciliationService = app(SupplierLedgerReconciliationService::class);
        $reconciliation = $reconciliationService->reconcile();

        $this->assertTrue($reconciliation['is_matched']);
        $this->assertEquals('0.0000', $reconciliation['difference']);
        $this->assertEquals('2000.0000', $reconciliation['supplier_subledger_total_base']);
        $this->assertEquals('2000.0000', $reconciliation['gl_accounts_payable_total_base']);
    }

    public function test_posting_is_idempotent_and_does_not_create_duplicate_journal_entry(): void
    {
        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'INV-2026-0002',
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'status' => PurchaseInvoiceStatus::Draft,
            'subtotal' => 1000.0,
            'tax_total' => 0.0,
            'discount_total' => 0.0,
            'total' => 1000.0,
            'invoice_date' => now()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_variant_id' => $this->variant->id,
            'unit_id' => $this->unit->id,
            'quantity' => 5,
            'unit_price' => 200.0,
            'subtotal' => 1000.0,
            'total' => 1000.0,
        ]);

        $action = app(PostPurchaseInvoiceAction::class);
        $action->execute($invoice);

        $initialCount = JournalEntry::where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->count();
        $this->assertEquals(1, $initialCount);

        // Re-execute builder directly
        $builder = app(BuildJournalFromPurchaseInvoiceAction::class);
        $secondResult = $builder->execute($invoice->fresh());

        $this->assertEquals(1, JournalEntry::where('source_type', PurchaseInvoice::class)
            ->where('source_id', $invoice->id)
            ->count());
    }
}
