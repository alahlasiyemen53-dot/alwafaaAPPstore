<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountType;
use App\Enums\ApiErrorCode;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Enums\TreasuryTransactionStatus;
use App\Enums\TreasuryTransactionType;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPaymentMethod;
use App\Models\DeliveryMethod;
use App\Models\District;
use App\Models\ElectronicWalletAccount;
use App\Models\Governorate;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\TreasuryTransaction;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Payments\Providers\FakePaymentProvider;
use App\Services\Auth\CustomerJwtService;
use Database\Seeders\AccountingMappingSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CustomerPaymentMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Currency $currency;

    private Warehouse $warehouse;

    private Governorate $governorate;

    private City $city;

    private District $district;

    private Brand $brand;

    private Category $category;

    private Supplier $supplier;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payments.driver', 'fake');
        config()->set('payments.providers', [
            'jaib' => FakePaymentProvider::class,
            'floosak' => FakePaymentProvider::class,
        ]);

        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->jwtService = app(CustomerJwtService::class);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(
            ['key' => 'default_currency_id'],
            ['value' => (string) $this->currency->id]
        );

        $this->country = Country::query()->where('code', 'YE')->firstOrFail();

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            [
                'name_ar' => 'توصيل قياسي',
                'name_en' => 'Standard Delivery',
                'price' => 1500,
                'estimated_delivery_ar' => 'خلال 24-48 ساعة',
                'estimated_delivery_en' => 'Within 24-48 hours',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->governorate = Governorate::create([
            'country_id' => $this->country->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa-payment-api',
            'is_active' => true,
        ]);

        $this->ensureStandardDeliveryAssignment($this->governorate->id, 1500.0);

        $this->city = City::create([
            'governorate_id' => $this->governorate->id,
            'name_ar' => 'الأمانة',
            'name_en' => 'Al-Amanah',
            'slug' => 'al-amanah-payment',
            'is_active' => true,
        ]);

        $this->district = District::create([
            'city_id' => $this->city->id,
            'name_ar' => 'السبعين',
            'name_en' => 'Al-Sabeen',
            'slug' => 'al-sabeen-payment',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي - صنعاء',
            'code' => 'WH-SANAA-PAY',
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'علامة تجارية',
            'name_en' => 'Brand Test',
            'slug' => 'brand-payment-test',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'قسم تجريبي',
            'name_en' => 'Category Test',
            'slug' => 'category-payment-test',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'مورد التوفير السريع',
            'code' => 'SUPP-PAY',
            'contact_person' => 'أحمد المورد',
            'phone' => '770000001',
            'is_active' => true,
        ]);

        $this->seed(CustomerPaymentMethodSeeder::class);
        $this->seedOnlineWalletAccounting();
    }

    private function seedOnlineWalletAccounting(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(AccountingMappingSeeder::class);

        AccountingPeriod::query()->firstOrCreate(
            [
                'starts_at' => now()->startOfMonth()->toDateString(),
                'ends_at' => now()->endOfMonth()->toDateString(),
            ],
            [
                'name' => 'فترة اختبار المدفوعات',
                'status' => AccountingPeriodStatus::Open,
            ]
        );

        $jaibGl = Account::query()->create([
            'code' => '1181',
            'name_ar' => 'محفظة جيب اختبار',
            'name_en' => 'Jaib Wallet Test',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $floosakGl = Account::query()->create([
            'code' => '1182',
            'name_ar' => 'محفظة فلوسك اختبار',
            'name_en' => 'Floosak Wallet Test',
            'type' => AccountType::Asset,
            'normal_balance' => AccountNormalBalance::Debit,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $jaibWallet = ElectronicWalletAccount::query()->create([
            'code' => 'WLT-JAIB-TEST',
            'provider_name' => 'Jaib',
            'account_label' => 'محفظة جيب',
            'account_identifier' => 'jaib-test',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $jaibGl->id,
            'is_active' => true,
        ]);

        $floosakWallet = ElectronicWalletAccount::query()->create([
            'code' => 'WLT-FLOOSAK-TEST',
            'provider_name' => 'Floosak',
            'account_label' => 'محفظة فلوسك',
            'account_identifier' => 'floosak-test',
            'currency_id' => $this->currency->id,
            'gl_account_id' => $floosakGl->id,
            'is_active' => true,
        ]);

        CustomerPaymentMethod::query()->where('code', 'jaib')->update([
            'electronic_wallet_account_id' => $jaibWallet->id,
        ]);
        CustomerPaymentMethod::query()->where('code', 'floosak')->update([
            'electronic_wallet_account_id' => $floosakWallet->id,
        ]);
    }

    public function test_cod_order_still_works(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method.code', 'cod')
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Unpaid->value);
    }

    public function test_jaib_order_with_valid_payment_succeeds_as_paid(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'jaib',
                'shipping_method' => 'standard',
                'payment' => [
                    'code' => '123456',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment_method.code', 'jaib')
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Paid->value)
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

        $this->assertNotNull($response->json('data.payment_provider_reference'));

        $order = Order::query()->firstOrFail();
        $this->assertNotNull($order->treasury_transaction_id);

        $trx = TreasuryTransaction::query()->findOrFail($order->treasury_transaction_id);
        $this->assertSame(TreasuryTransactionType::WalletReceipt, $trx->transaction_type);
        $this->assertSame(TreasuryTransactionStatus::Posted, $trx->status);
        $this->assertSame(ElectronicWalletAccount::class, $trx->account_type);
    }

    public function test_jaib_order_with_fail_code_is_rejected(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'jaib',
                'shipping_method' => 'standard',
                'payment' => [
                    'code' => '000000',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::PaymentFailed->value);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_floosak_initiate_returns_pending_otp_attempt(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/initiate', [
                'customer_address_id' => $address->id,
                'payment_method' => 'floosak',
                'shipping_method' => 'standard',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending_otp')
            ->assertJsonPath('data.provider_code', 'floosak')
            ->assertJsonPath('data.payment_method.code', 'floosak');

        $this->assertNotNull($response->json('data.uuid'));
    }

    public function test_floosak_confirm_with_valid_otp_creates_paid_order(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $initiate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/initiate', [
                'customer_address_id' => $address->id,
                'payment_method' => 'floosak',
                'shipping_method' => 'standard',
            ]);

        $uuid = (string) $initiate->json('data.uuid');

        $confirm = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/payments/{$uuid}/confirm", [
                'otp' => '123456',
            ]);

        $confirm->assertStatus(201)
            ->assertJsonPath('data.payment_method.code', 'floosak')
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Paid->value)
            ->assertJsonPath('data.status', OrderStatus::Confirmed->value);

        $order = Order::query()->firstOrFail();
        $this->assertNotNull($order->treasury_transaction_id);
        $trx = TreasuryTransaction::query()->findOrFail($order->treasury_transaction_id);
        $this->assertSame(TreasuryTransactionType::WalletReceipt, $trx->transaction_type);
        $this->assertSame(TreasuryTransactionStatus::Posted, $trx->status);
    }

    public function test_floosak_confirm_with_wrong_otp_fails(): void
    {
        [$customer, $token, $address, $variant] = $this->prepareCheckoutCart();

        $initiate = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/payments/initiate', [
                'customer_address_id' => $address->id,
                'payment_method' => 'floosak',
                'shipping_method' => 'standard',
            ]);

        $uuid = (string) $initiate->json('data.uuid');

        $confirm = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/payments/{$uuid}/confirm", [
                'otp' => '000000',
            ]);

        $confirm->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::PaymentFailed->value);

        $this->assertSame(0, Order::query()->count());
    }

    /**
     * @return array{0: Customer, 1: string, 2: CustomerAddress, 3: ProductVariant}
     */
    private function prepareCheckoutCart(): array
    {
        $customer = $this->createCustomer('771555555');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(4000.0, 10.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        return [$customer, $token, $address, $variant];
    }

    private function createCustomer(string $phone): Customer
    {
        return Customer::create([
            'phone' => $phone,
            'normalized_phone' => $phone,
            'name' => 'عميل دفع '.substr($phone, -4),
            'is_active' => true,
        ]);
    }

    private function createAddress(Customer $customer, bool $isDefault = false): CustomerAddress
    {
        return CustomerAddress::create([
            'customer_id' => $customer->id,
            'country_id' => $this->country->id,
            'label' => 'المنزل',
            'contact_name' => $customer->name,
            'contact_phone' => $customer->phone,
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'neighborhood' => 'حي السبعين',
            'street' => 'شارع حدة',
            'building' => 'عمارة 10',
            'formatted_address' => 'صنعاء - السبعين - شارع حدة',
            'is_default' => $isDefault,
        ]);
    }

    private function createStockedVariant(float $price, float $stock): ProductVariant
    {
        $product = Product::create([
            'name_ar' => 'منتج مخزني '.Str::random(5),
            'slug' => 'stocked-pay-'.Str::random(8),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'status' => ProductStatus::Active,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PAY-'.Str::upper(Str::random(6)),
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => $price,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => $stock,
            'reserved_quantity' => 0.0,
        ]);

        return $variant;
    }
}
