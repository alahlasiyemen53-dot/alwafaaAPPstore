<?php

namespace Tests\Feature;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\DTOs\ReportDateRangeData;
use App\DTOs\ReportFilterData;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\ExchangeRate;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Queries\Reporting\MarketingReportQuery;
use App\Queries\Reporting\SalesReportQuery;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesAndMarketingReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private CustomerAddress $address;

    private Warehouse $warehouse;

    private Currency $yer;

    private Currency $usd;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);

        $this->admin = $this->userWithRole('admin');

        $this->yer = Currency::create(['code' => 'YER', 'name_ar' => 'ريال يمني', 'symbol' => 'ر.ي', 'decimal_places' => 2, 'is_default' => true, 'is_active' => true]);
        $this->usd = Currency::create(['code' => 'USD', 'name_ar' => 'دولار', 'symbol' => '$', 'decimal_places' => 2, 'is_default' => false, 'is_active' => true]);

        $this->customer = Customer::create(['name' => 'عميل التقارير', 'phone' => '771122334', 'normalized_phone' => '+967771122334', 'is_active' => true]);
        $this->address = CustomerAddress::create(['customer_id' => $this->customer->id, 'label' => 'المنزل', 'contact_name' => 'عميل التقارير', 'contact_phone' => '771122334', 'neighborhood' => 'حدة', 'is_default' => true]);
        $this->warehouse = Warehouse::create(['name_ar' => 'مستودع التقارير', 'code' => 'WH-REP', 'is_active' => true]);

        $unit = Unit::create(['code' => 'PCS-R', 'name_ar' => 'حبة', 'decimal_places' => 0, 'is_active' => true]);
        $brand = Brand::create(['name_ar' => 'ماركة تقارير', 'slug' => 'rep-brand', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'صنف تقارير', 'slug' => 'rep-cat', 'is_active' => true]);
        $product = Product::create(['name_ar' => 'منتج تقارير', 'slug' => 'rep-prod', 'brand_id' => $brand->id, 'category_id' => $category->id, 'status' => 'active']);

        $this->variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-REP-001', 'status' => 'active', 'is_default' => true]);

        VariantPrice::create(['product_variant_id' => $this->variant->id, 'currency_id' => $this->yer->id, 'price' => 1000.0000, 'is_active' => true]);
        VariantPrice::create(['product_variant_id' => $this->variant->id, 'currency_id' => $this->usd->id, 'price' => 5.0000, 'is_active' => true]);

        ExchangeRate::create([
            'from_currency_id' => $this->yer->id,
            'to_currency_id' => $this->usd->id,
            'rate' => 0.004,
            'effective_at' => now()->subDay(),
        ]);

        Inventory::create(['warehouse_id' => $this->warehouse->id, 'product_variant_id' => $this->variant->id, 'quantity' => 100.0000, 'reserved_quantity' => 0.0000]);
    }

    private function confirmOrder(Currency $currency, float $quantity = 1.0): Order
    {
        $dto = new CreateOrderData(
            customerId: $this->customer->id,
            customerAddressId: $this->address->id,
            warehouseId: $this->warehouse->id,
            currencyId: $currency->id,
            items: [new OrderItemData(productVariantId: $this->variant->id, quantity: $quantity)],
        );

        $order = app(CreateOrderAction::class)->execute($dto, $this->admin->id);

        return app(ConfirmOrderAction::class)->execute($order, $this->admin->id);
    }

    private function filters(?int $currencyId = null): ReportFilterData
    {
        return new ReportFilterData(
            range: ReportDateRangeData::fromPreset('custom', now()->subDay()->toDateString(), now()->addDay()->toDateString()),
            currencyId: $currencyId,
            warehouseId: null,
            customerId: null,
            supplierId: null,
            driverId: null,
            sort: 'confirmed_at',
            direction: 'desc',
            perPage: 20,
        );
    }

    public function test_sales_report_separates_currencies_and_excludes_cancelled_orders(): void
    {
        $this->confirmOrder($this->yer, 2.0);
        $this->confirmOrder($this->usd, 3.0);

        $cancelled = $this->confirmOrder($this->yer, 1.0);
        app(CancelOrderAction::class)->execute($cancelled, new CancelOrderData('طلب تجريبي ملغى'), $this->admin->id);

        $report = app(SalesReportQuery::class)->execute($this->filters());

        $byCurrency = collect($report['summary'])->keyBy('currency_code');

        $this->assertSame(1, $byCurrency['YER']['orders_count']);
        $this->assertSame(0, bccomp($byCurrency['YER']['net_total'], '2000.0000', 4));
        $this->assertSame(1, $byCurrency['USD']['orders_count']);
        $this->assertSame(0, bccomp($byCurrency['USD']['net_total'], '12.0000', 4));
    }

    public function test_sales_report_currency_filter_narrows_results(): void
    {
        $this->confirmOrder($this->yer, 1.0);
        $this->confirmOrder($this->usd, 1.0);

        $report = app(SalesReportQuery::class)->execute($this->filters($this->usd->id));

        $this->assertCount(1, $report['summary']);
        $this->assertSame('USD', $report['summary'][0]['currency_code']);
    }

    public function test_sales_report_date_range_excludes_orders_outside_window(): void
    {
        $this->confirmOrder($this->yer, 1.0);

        $futureFilters = new ReportFilterData(
            range: ReportDateRangeData::fromPreset('custom', now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString()),
            currencyId: null,
            warehouseId: null,
            customerId: null,
            supplierId: null,
            driverId: null,
            sort: 'confirmed_at',
            direction: 'desc',
            perPage: 20,
        );

        $report = app(SalesReportQuery::class)->execute($futureFilters);

        $this->assertSame([], $report['summary']);
    }

    public function test_marketing_report_uses_historical_discount_snapshot_after_promotion_changes(): void
    {
        $promo = Promotion::create([
            'code' => 'REPPROMO',
            'name' => 'عرض تقارير',
            'type' => PromotionType::Percentage,
            'scope' => PromotionScope::Variant,
            'value' => 10.00,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'priority' => 1,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $promo->id, 'target_type' => 'variant', 'target_id' => $this->variant->id]);

        $order = $this->confirmOrder($this->yer, 1.0);
        $this->assertSame('100.0000', (string) $order->promotion_discount_total);

        // Change the campaign after confirmation; historical report must not change.
        $promo->update(['value' => 50.00, 'is_active' => false]);

        $report = app(MarketingReportQuery::class)->execute($this->filters());

        $this->assertNotEmpty($report['discounts']);
        $this->assertSame(0, bccomp($report['discounts'][0]['promotion_discount_total'], '100.0000', 4));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
