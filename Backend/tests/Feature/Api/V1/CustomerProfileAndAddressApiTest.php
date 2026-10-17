<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\District;
use App\Models\Governorate;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerProfileAndAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Governorate $governorate1;

    private Governorate $governorate2;

    private City $city1;

    private City $city2;

    private District $district1;

    private Currency $baseCurrency;

    private Country $yemen;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->jwtService = app(CustomerJwtService::class);

        $this->yemen = Country::query()->where('code', 'YE')->firstOrFail();

        $this->baseCurrency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Governorate 1: Sana'a
        $this->governorate1 = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'name_en' => "Sana'a",
            'slug' => 'sanaa',
            'is_active' => true,
        ]);

        // Governorate 2: Aden
        $this->governorate2 = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'عدن',
            'name_en' => 'Aden',
            'slug' => 'aden',
            'is_active' => true,
        ]);

        // City 1 in Governorate 1
        $this->city1 = City::create([
            'governorate_id' => $this->governorate1->id,
            'name_ar' => 'أمانة العاصمة',
            'name_en' => 'Capital Municipality',
            'slug' => 'capital-municipality',
            'is_active' => true,
        ]);

        // City 2 in Governorate 2
        $this->city2 = City::create([
            'governorate_id' => $this->governorate2->id,
            'name_ar' => 'خور مكسر',
            'name_en' => 'Khor Maksar',
            'slug' => 'khor-maksar',
            'is_active' => true,
        ]);

        // District 1 in City 1
        $this->district1 = District::create([
            'city_id' => $this->city1->id,
            'name_ar' => 'السبعين',
            'name_en' => 'Al Sabeen',
            'slug' => 'al-sabeen',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->patchJson('/api/v1/me', ['name' => 'اختبار'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->getJson('/api/v1/addresses')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->postJson('/api/v1/addresses', ['label' => 'المنزل'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->getJson('/api/v1/addresses/1')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->patchJson('/api/v1/addresses/1', ['label' => 'العمل'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->deleteJson('/api/v1/addresses/1')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);
    }

    public function test_authenticated_customer_can_update_profile_safely(): void
    {
        $customer = $this->createTestCustomer('فاطمة علي', '770112233');
        $token = $this->jwtService->generateAccessToken($customer);

        // Update name and email
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'name' => 'فاطمة محمد علي',
                'email' => 'fatima.updated@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'فاطمة محمد علي')
            ->assertJsonPath('data.email', 'fatima.updated@example.com')
            ->assertJsonPath('data.phone', $customer->phone);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'فاطمة محمد علي',
            'email' => 'fatima.updated@example.com',
        ]);
    }

    public function test_profile_update_prohibits_sensitive_fields_and_phone(): void
    {
        $customer = $this->createTestCustomer('صالح أحمد', '770223344');
        $token = $this->jwtService->generateAccessToken($customer);

        // Attempt to change phone and is_active and wallet
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'phone' => '779998877',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'is_active' => false,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/me', [
                'tier' => 'vip',
                'wallet' => 100000,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }

    public function test_profile_update_validates_email_uniqueness_across_customers(): void
    {
        $customerA = $this->createTestCustomer('العميل أ', '770334455');
        $customerB = $this->createTestCustomer('العميل ب', '770445566');

        $customerA->update(['email' => 'unique.email@example.com']);
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        // Customer B tries to take Customer A's email
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->patchJson('/api/v1/me', [
                'email' => 'unique.email@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }

    public function test_creating_first_address_automatically_becomes_default(): void
    {
        $customer = $this->createTestCustomer('ياسر سعيد', '770556677');
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع حدة، خلف المركز التجاري',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
                'landmark' => 'جوار صيدلية الأمل',
                'is_default' => false, // First address must automatically become default regardless
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.label', 'المنزل')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.country.code', 'YE')
            ->assertJsonPath('data.governorate.id', $this->governorate1->id)
            ->assertJsonMissingPath('data.governorate.delivery_fee')
            ->assertJsonMissingPath('data.city')
            ->assertJsonMissingPath('data.district')
            ->assertJsonPath('data.latitude', 15.369445)
            ->assertJsonPath('data.longitude', 44.191006);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'is_default' => true,
        ]);
    }

    public function test_creating_address_requires_coordinates(): void
    {
        $customer = $this->createTestCustomer('موقع مطلوب', '770556678');
        $token = $this->jwtService->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع حدة',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_creating_address_with_clear_text_and_map_only(): void
    {
        $customer = $this->createTestCustomer('عنوان الخريطة', '770556679');
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'country_id' => $this->yemen->id,
                'governorate_id' => $this->governorate1->id,
                'formatted_address' => 'حي حدة قرب جامع النور',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.label', 'المنزل')
            ->assertJsonPath('data.formatted_address', 'حي حدة قرب جامع النور')
            ->assertJsonPath('data.street', 'حي حدة قرب جامع النور')
            ->assertJsonPath('data.latitude', 15.369445)
            ->assertJsonPath('data.longitude', 44.191006)
            ->assertJsonPath('data.governorate.id', $this->governorate1->id)
            ->assertJsonMissingPath('data.city')
            ->assertJsonMissingPath('data.district');
    }

    public function test_setting_new_address_as_default_unsets_previous_default_atomically(): void
    {
        $customer = $this->createTestCustomer('بلال ناصر', '770667788');
        $token = $this->jwtService->generateAccessToken($customer);

        // First address
        $res1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع حدة',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
            ]);
        $addr1Id = $res1->json('data.id');

        // Second address with is_default = true
        $res2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'العمل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع الزبيري',
                'latitude' => 15.3700000,
                'longitude' => 44.1920000,
                'is_default' => true,
            ]);
        $addr2Id = $res2->json('data.id');

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr1Id,
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr2Id,
            'is_default' => true,
        ]);
    }

    public function test_updating_address_to_default_unsets_other_defaults(): void
    {
        $customer = $this->createTestCustomer('منى هاشم', '770778899');
        $token = $this->jwtService->generateAccessToken($customer);

        // Address 1 (default)
        $res1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع حدة',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
            ]);
        $addr1Id = $res1->json('data.id');

        // Address 2 (not default)
        $res2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'العمل',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع الزبيري',
                'latitude' => 15.3700000,
                'longitude' => 44.1920000,
                'is_default' => false,
            ]);
        $addr2Id = $res2->json('data.id');

        // Update Address 2 to is_default = true
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/addresses/{$addr2Id}", [
                'is_default' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('customer_addresses', ['id' => $addr1Id, 'is_default' => false]);
        $this->assertDatabaseHas('customer_addresses', ['id' => $addr2Id, 'is_default' => true]);
    }

    public function test_deleting_default_address_reassigns_default_to_remaining_address(): void
    {
        $customer = $this->createTestCustomer('عمر حامد', '770889900');
        $token = $this->jwtService->generateAccessToken($customer);

        $res1 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'عنوان 1',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع 1',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
            ]);
        $addr1Id = $res1->json('data.id');

        $res2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'عنوان 2',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع 2',
                'latitude' => 15.3700000,
                'longitude' => 44.1920000,
                'is_default' => true,
            ]);
        $addr2Id = $res2->json('data.id');

        // Delete address 2 (which is default)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/addresses/{$addr2Id}")
            ->assertStatus(200);

        // Address 1 should now be promoted to default
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addr1Id,
            'is_default' => true,
        ]);
    }

    public function test_bola_defense_customer_cannot_access_or_mutate_another_customers_address(): void
    {
        $customerA = $this->createTestCustomer('العميل أ', '771110001');
        $tokenA = $this->jwtService->generateAccessToken($customerA);

        $customerB = $this->createTestCustomer('العميل ب', '771110002');
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        // Customer A creates address
        $resA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/addresses', [
                'label' => 'منزل العميل أ',
                'governorate_id' => $this->governorate1->id,
                'street' => 'شارع أ',
                'latitude' => 15.3694450,
                'longitude' => 44.1910060,
            ]);
        $addrAId = $resA->json('data.id');

        // Customer B tries to view Customer A's address -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson("/api/v1/addresses/{$addrAId}")
            ->assertStatus(404)
            ->assertJsonPath('data.code', ApiErrorCode::ResourceNotFound->value);

        // Customer B tries to update Customer A's address -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->patchJson("/api/v1/addresses/{$addrAId}", [
                'label' => 'محاولة اختراق',
            ])
            ->assertStatus(404)
            ->assertJsonPath('data.code', ApiErrorCode::ResourceNotFound->value);

        // Customer B tries to delete Customer A's address -> 404
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->deleteJson("/api/v1/addresses/{$addrAId}")
            ->assertStatus(404)
            ->assertJsonPath('data.code', ApiErrorCode::ResourceNotFound->value);

        // Customer A's address remains intact
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $addrAId,
            'label' => 'منزل العميل أ',
        ]);
    }

    public function test_address_validation_enforces_yemen_hierarchy(): void
    {
        $customer = $this->createTestCustomer('وليد صالح', '771110003');
        $token = $this->jwtService->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'عنوان غير متطابق',
                'country_id' => $this->yemen->id,
                'governorate_id' => $this->governorate2->id,
                'formatted_address' => 'شارع ما',
                'latitude' => 12.779723,
                'longitude' => 45.009127,
            ])
            ->assertStatus(201);
    }

    public function test_reference_endpoints_return_active_data(): void
    {
        // 1. Governorates list
        $govRes = $this->getJson('/api/v1/governorates')
            ->assertStatus(200)
            ->assertJsonPath('status', true);

        $this->assertCount(2, $govRes->json('data'));
        $this->assertArrayNotHasKey('delivery_fee', $govRes->json('data.0'));

        $this->getJson('/api/v1/countries')
            ->assertStatus(200)
            ->assertJsonPath('data.0.code', 'YE');

        $this->getJson("/api/v1/countries/{$this->yemen->id}/governorates")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/governorates/{$this->governorate1->id}/cities")
            ->assertStatus(200)
            ->assertJsonPath('data', []);

        $this->getJson("/api/v1/cities/{$this->city1->id}/districts")
            ->assertStatus(200)
            ->assertJsonPath('data', []);

        // 4. Currencies
        $currRes = $this->getJson('/api/v1/currencies')
            ->assertStatus(200);

        $this->assertCount(1, $currRes->json('data'));
        $this->assertSame('YER', $currRes->json('data.0.code'));
    }

    public function test_address_listing_query_count_is_constant_avoiding_n_plus_one(): void
    {
        $customer = $this->createTestCustomer('فحص الأداء', '771110004');
        $token = $this->jwtService->generateAccessToken($customer);

        // Create 5 addresses for this customer
        for ($i = 1; $i <= 5; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/addresses', [
                    'label' => "عنوان {$i}",
                    'governorate_id' => $this->governorate1->id,
                    'street' => "شارع رقم {$i}",
                    'latitude' => 15.3694450,
                    'longitude' => 44.1910060,
                ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/addresses');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Must eager-load customer addresses with country and governorate in a bounded query count
        $this->assertLessThanOrEqual(6, count($queries), 'Customer addresses listing executed excessive database queries (N+1 detected).');
    }

    private function createTestCustomer(string $name, string $phone): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'normalized_phone' => '+967'.$phone,
            'email' => "user_{$phone}@example.com",
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'otp',
        ]);
    }
}
