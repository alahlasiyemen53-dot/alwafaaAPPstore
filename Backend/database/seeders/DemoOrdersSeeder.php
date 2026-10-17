<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmOrderAction;
use App\Actions\CreateOrderAction;
use App\DTOs\CancelOrderData;
use App\DTOs\CreateOrderData;
use App\DTOs\OrderItemData;
use App\Enums\ProductVariantFulfillmentMode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\GiftWrappingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DemoOrdersSeeder extends Seeder
{
    private const DEMO_NOTE_MARKER = '[بيانات تجريبية]';

    public function run(): void
    {
        if (Order::query()->where('notes', 'like', '%'.self::DEMO_NOTE_MARKER.'%')->exists()) {
            $this->command?->info('بيانات الطلبات التجريبية موجودة مسبقاً — تم التخطي.');

            return;
        }

        $currency = Currency::query()->where('is_default', true)->first()
            ?? Currency::query()->where('code', 'YER')->firstOrFail();

        $warehouse = Warehouse::query()->firstOrCreate(
            ['code' => 'WH-SAN-MAIN'],
            [
                'name_ar' => 'المستودع الرئيسي - صنعاء',
                'name_en' => 'Main Warehouse - Sanaa',
                'is_active' => true,
            ]
        );

        $unit = Unit::query()->where('code', 'piece')->first()
            ?? Unit::query()->firstOrCreate(
                ['code' => 'piece'],
                ['name_ar' => 'قطعة', 'name_en' => 'Piece', 'decimal_places' => 0, 'is_active' => true]
            );

        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'demo-wafaa'],
            ['name_ar' => 'الوفاء استور', 'name_en' => "AL-Wafa'a Store", 'is_active' => true, 'sort_order' => 1]
        );

        $category = Category::query()->firstOrCreate(
            ['slug' => 'demo-gifts'],
            ['name_ar' => 'هدايا وتجميل', 'name_en' => 'Gifts & Beauty', 'is_active' => true, 'sort_order' => 1]
        );

        $variants = $this->seedCatalog($brand->id, $category->id, $currency->id, $warehouse->id);
        $customers = $this->seedCustomers();

        Setting::updateOrCreate(['key' => 'gift_wrapping_enabled'], ['value' => '1', 'type' => 'boolean']);
        Setting::updateOrCreate(['key' => 'gift_message_enabled'], ['value' => '1', 'type' => 'boolean']);
        Setting::updateOrCreate(['key' => 'gift_message_max_length'], ['value' => '250', 'type' => 'integer']);
        app(GiftWrappingService::class)->clearCache();

        $adminId = User::query()->orderBy('id')->value('id');

        $create = app(CreateOrderAction::class);
        $confirm = app(ConfirmOrderAction::class);
        $cancel = app(CancelOrderAction::class);

        // 1) مسودة — بدون تأكيد
        $create->execute(new CreateOrderData(
            customerId: $customers[0]['customer']->id,
            customerAddressId: $customers[0]['address']->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [
                new OrderItemData($variants['perfume']->id, 1, $unit->id),
                new OrderItemData($variants['candle']->id, 2, $unit->id),
            ],
            notes: self::DEMO_NOTE_MARKER.' مسودة بانتظار المراجعة',
            shippingMethod: 'standard',
        ), $adminId);

        // 2) مؤكد — طلب عادي
        $confirmed = $create->execute(new CreateOrderData(
            customerId: $customers[1]['customer']->id,
            customerAddressId: $customers[1]['address']->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [
                new OrderItemData($variants['watch']->id, 1, $unit->id),
                new OrderItemData($variants['earbuds']->id, 1, $unit->id),
            ],
            notes: self::DEMO_NOTE_MARKER.' طلب مؤكد جاهز للتجهيز',
            shippingMethod: 'express',
            deliveryFeeBase: 3000,
        ), $adminId);
        $confirm->execute($confirmed, $adminId);

        // 3) مؤكد مع تغليف هدية
        $giftOrder = $create->execute(new CreateOrderData(
            customerId: $customers[2]['customer']->id,
            customerAddressId: $customers[2]['address']->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [
                new OrderItemData($variants['perfume']->id, 1, $unit->id),
                new OrderItemData($variants['scarf']->id, 1, $unit->id),
            ],
            notes: self::DEMO_NOTE_MARKER.' طلب هدية مع تغليف',
            giftWrappingRequested: true,
            giftMessage: 'كل عام وأنتِ بخير ❤️',
            shippingMethod: 'same_day',
            deliveryFeeBase: 5000,
        ), $adminId);
        $confirm->execute($giftOrder, $adminId);

        // 4) مسودة ثم إلغاء
        $toCancel = $create->execute(new CreateOrderData(
            customerId: $customers[0]['customer']->id,
            customerAddressId: $customers[0]['address']->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [
                new OrderItemData($variants['candle']->id, 3, $unit->id),
            ],
            notes: self::DEMO_NOTE_MARKER.' طلب ملغي للتجربة',
            shippingMethod: 'standard',
        ), $adminId);
        $cancel->execute($toCancel, new CancelOrderData('إلغاء تجريبي — العميل تراجع عن الطلب'), $adminId);

        // 5) مسودة ثانية متعددة الأصناف
        $create->execute(new CreateOrderData(
            customerId: $customers[1]['customer']->id,
            customerAddressId: $customers[1]['address']->id,
            warehouseId: $warehouse->id,
            currencyId: $currency->id,
            items: [
                new OrderItemData($variants['earbuds']->id, 2, $unit->id),
                new OrderItemData($variants['scarf']->id, 2, $unit->id),
                new OrderItemData($variants['candle']->id, 1, $unit->id),
            ],
            notes: self::DEMO_NOTE_MARKER.' مسودة سلة متنوعة',
            shippingMethod: 'standard',
            deliveryFeeBase: 1500,
        ), $adminId);

        $count = Order::query()->where('notes', 'like', '%'.self::DEMO_NOTE_MARKER.'%')->count();
        $this->command?->info("تم إنشاء {$count} طلبات تجريبية بنجاح.");
    }

    /**
     * @return array{perfume: ProductVariant, watch: ProductVariant, earbuds: ProductVariant, candle: ProductVariant, scarf: ProductVariant}
     */
    private function seedCatalog(int $brandId, int $categoryId, int $currencyId, int $warehouseId): array
    {
        $catalog = [
            'perfume' => ['name_ar' => 'عطر الوفاء استور الذهبي', 'name_en' => "AL-Wafa'a Store Golden Perfume", 'sku' => 'DEMO-PERFUME-01', 'price' => 18500, 'qty' => 80],
            'watch' => ['name_ar' => 'ساعة يد أنيقة', 'name_en' => 'Elegant Wrist Watch', 'sku' => 'DEMO-WATCH-01', 'price' => 32000, 'qty' => 40],
            'earbuds' => ['name_ar' => 'سماعات لاسلكية', 'name_en' => 'Wireless Earbuds', 'sku' => 'DEMO-EARBUD-01', 'price' => 12500, 'qty' => 100],
            'candle' => ['name_ar' => 'شمعة معطرة فاخرة', 'name_en' => 'Luxury Scented Candle', 'sku' => 'DEMO-CANDLE-01', 'price' => 4500, 'qty' => 120],
            'scarf' => ['name_ar' => 'وشاح حريري', 'name_en' => 'Silk Scarf', 'sku' => 'DEMO-SCARF-01', 'price' => 9800, 'qty' => 60],
        ];

        $variants = [];

        foreach ($catalog as $key => $item) {
            $product = Product::query()->firstOrCreate(
                ['slug' => Str::slug($item['sku'])],
                [
                    'brand_id' => $brandId,
                    'category_id' => $categoryId,
                    'name_ar' => $item['name_ar'],
                    'name_en' => $item['name_en'],
                    'status' => 'active',
                    'has_variants' => false,
                    'is_new' => true,
                ]
            );

            $variant = ProductVariant::query()->firstOrCreate(
                ['sku' => $item['sku']],
                [
                    'product_id' => $product->id,
                    'status' => 'active',
                    'is_default' => true,
                    'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
                ]
            );

            VariantPrice::query()->updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'currency_id' => $currencyId,
                ],
                [
                    'price' => $item['price'],
                    'is_active' => true,
                ]
            );

            Inventory::query()->updateOrCreate(
                [
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'quantity' => $item['qty'],
                    'reserved_quantity' => 0,
                ]
            );

            $variants[$key] = $variant;
        }

        return $variants;
    }

    /**
     * @return list<array{customer: Customer, address: CustomerAddress}>
     */
    private function seedCustomers(): array
    {
        $sanaa = Governorate::query()->where('name_ar', 'أمانة العاصمة')->first()
            ?? Governorate::query()->firstOrFail();

        $aden = Governorate::query()->where('name_ar', 'عدن')->first() ?? $sanaa;

        $profiles = [
            [
                'phone' => '770100001',
                'normalized' => '+967770100001',
                'name' => 'سارة محمد',
                'label' => 'المنزل',
                'gov' => $sanaa,
                'address' => 'صنعاء - حدة - شارع الستين قرب جامع النور',
                'neighborhood' => 'حدة',
                'street' => 'شارع الستين',
            ],
            [
                'phone' => '770100002',
                'normalized' => '+967770100002',
                'name' => 'خالد العزي',
                'label' => 'العمل',
                'gov' => $sanaa,
                'address' => 'صنعاء - السبعين - جوار مول سبأ',
                'neighborhood' => 'السبعين',
                'street' => 'شارع الخمسين',
            ],
            [
                'phone' => '770100003',
                'normalized' => '+967770100003',
                'name' => 'نورة أحمد',
                'label' => 'المنزل',
                'gov' => $aden,
                'address' => 'عدن - المنصورة - شارع الأمير',
                'neighborhood' => 'المنصورة',
                'street' => 'شارع الأمير',
            ],
        ];

        $result = [];

        foreach ($profiles as $profile) {
            $customer = Customer::query()->updateOrCreate(
                ['normalized_phone' => $profile['normalized']],
                [
                    'name' => $profile['name'],
                    'phone' => $profile['phone'],
                    'is_active' => true,
                ]
            );

            $address = CustomerAddress::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'label' => $profile['label'],
                ],
                [
                    'contact_name' => $profile['name'],
                    'contact_phone' => $profile['phone'],
                    'governorate_id' => $profile['gov']->id,
                    'neighborhood' => $profile['neighborhood'],
                    'street' => $profile['street'],
                    'formatted_address' => $profile['address'],
                    'is_default' => true,
                ]
            );

            $result[] = ['customer' => $customer, 'address' => $address];
        }

        return $result;
    }
}
