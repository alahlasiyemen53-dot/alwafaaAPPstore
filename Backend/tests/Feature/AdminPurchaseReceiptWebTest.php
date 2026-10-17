<?php

namespace Tests\Feature;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReceiptStatus;
use App\Enums\StockMovementDirection;
use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReceipt;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPurchaseReceiptWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private ProductVariant $variant;

    private PurchaseInvoice $invoice;

    private PurchaseInvoiceItem $invoiceItem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-TEST-RC',
            'name_ar' => 'مستودع الاستلام',
            'is_active' => true,
        ]);

        $currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $unit = Unit::create(['code' => 'PCS', 'name_ar' => 'حبة', 'is_active' => true]);
        $supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'المورد الأول', 'phone' => '777111222', 'is_active' => true]);

        $brand = Brand::create(['name_ar' => 'علامة 1', 'slug' => 'brand-1', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'تصنيف 1', 'slug' => 'cat-1', 'is_active' => true]);

        $product = Product::create([
            'name_ar' => 'شاي ممتاز',
            'slug' => 'tea-premium',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEA-001',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->invoice = PurchaseInvoice::create([
            'invoice_number' => 'PI-2026-00001',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $currency->id,
            'invoice_date' => now()->toDateString(),
            'status' => PurchaseInvoiceStatus::Posted, // POSTED
            'exchange_rate' => 1.0,
            'subtotal' => 1000.0,
            'discount_amount' => 0.0,
            'other_costs' => 0.0,
            'total' => 1000.0,
            'posted_at' => now(),
        ]);

        // 100 purchased + 20 free = 120 total physical receivable
        $this->invoiceItem = PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $this->invoice->id,
            'product_variant_id' => $this->variant->id,
            'unit_id' => $unit->id,
            'quantity' => 100.0,
            'base_quantity' => 100.0,
            'free_quantity' => 20.0,
            'unit_cost' => 10.0,
            'line_subtotal' => 1000.0,
            'line_total' => 1000.0,
        ]);

        $this->admin = User::create([
            'name' => 'مسؤول الاستلام',
            'email' => 'rc-admin@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->addRole(Role::where('name', 'super_admin')->first());
    }

    public function test_cannot_receive_draft_purchase_invoice(): void
    {
        $this->invoice->update(['status' => PurchaseInvoiceStatus::Draft]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-receipts.store'), [
            'purchase_invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'purchase_invoice_item_id' => $this->invoiceItem->id,
                    'product_variant_id' => $this->variant->id,
                    'received_base_quantity' => 50,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('purchase_receipts', 0);
    }

    public function test_over_receipt_is_rejected(): void
    {
        // Total receivable is 120; attempting to receive 130
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-receipts.store'), [
            'purchase_invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'purchase_invoice_item_id' => $this->invoiceItem->id,
                    'product_variant_id' => $this->variant->id,
                    'received_base_quantity' => 130,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('purchase_receipts', 0);
    }

    public function test_create_and_post_purchase_receipt_updates_inventory_and_logs_movement(): void
    {
        // 1. Create Draft Receipt for 70
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-receipts.store'), [
            'purchase_invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'purchase_invoice_item_id' => $this->invoiceItem->id,
                    'product_variant_id' => $this->variant->id,
                    'received_base_quantity' => 70,
                ],
            ],
        ]);

        $receipt = PurchaseReceipt::first();
        $this->assertNotNull($receipt);
        $this->assertSame(PurchaseReceiptStatus::Draft, $receipt->status);
        $response->assertRedirect(route('admin.purchase-receipts.show', $receipt));

        // Before posting: inventory is 0
        $this->assertDatabaseMissing('inventories', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
        ]);

        // 2. Post the Receipt
        $postResponse = $this->actingAs($this->admin)->post(route('admin.purchase-receipts.post', $receipt));
        $postResponse->assertRedirect(route('admin.purchase-receipts.show', $receipt));

        $receipt->refresh();
        $this->assertSame(PurchaseReceiptStatus::Posted, $receipt->status);
        $this->assertNotNull($receipt->received_at);

        // Inventory should now have 70 on hand
        $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(70.0, (float) $inventory->quantity);
        $this->assertEquals(70.0, (float) $inventory->available_quantity);

        // StockMovement must be created
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'movement_type' => StockMovementType::PurchaseReceipt->value,
            'direction' => StockMovementDirection::In->value,
            'quantity' => 70.0,
        ]);

        // 3. Partial Receiving: check remaining quantity
        $this->invoiceItem->refresh();
        $this->assertEquals(70.0, $this->invoiceItem->already_received_quantity);
        $this->assertEquals(50.0, $this->invoiceItem->remaining_receivable_quantity);

        // 4. Create second receipt for remaining 50
        $this->actingAs($this->admin)->post(route('admin.purchase-receipts.store'), [
            'purchase_invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'purchase_invoice_item_id' => $this->invoiceItem->id,
                    'product_variant_id' => $this->variant->id,
                    'received_base_quantity' => 50,
                ],
            ],
        ]);

        $receipt2 = PurchaseReceipt::where('id', '!=', $receipt->id)->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.purchase-receipts.post', $receipt2));

        $inventory->refresh();
        $this->assertEquals(120.0, (float) $inventory->quantity);

        $this->invoiceItem->refresh();
        $this->assertEquals(120.0, $this->invoiceItem->already_received_quantity);
        $this->assertEquals(0.0, $this->invoiceItem->remaining_receivable_quantity);
    }
}
