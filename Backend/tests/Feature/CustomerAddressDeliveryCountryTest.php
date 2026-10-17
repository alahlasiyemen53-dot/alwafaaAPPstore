<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Governorate;
use App\Services\Auth\CustomerJwtService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerAddressDeliveryCountryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake(['*/health' => Http::response(['status' => 'unavailable'], 503)]);
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class]);

        Currency::firstOrCreate(
            ['code' => 'YER'],
            [
                'name_ar' => 'ريال يمني',
                'name_en' => 'Yemeni Rial',
                'symbol' => 'ر.ي',
                'decimal_places' => 2,
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }

    public function test_reference_data_returns_delivery_active_status_and_filters(): void
    {
        // SA is active for registration, but not for delivery
        $sa = Country::query()->create([
            'code' => 'SA',
            'name_ar' => 'المملكة العربية السعودية',
            'name_en' => 'Saudi Arabia',
            'calling_code' => '+966',
            'sort_order' => 2,
            'is_active' => true,
            'is_delivery_active' => false,
        ]);

        // General countries list returns both SA and YE with is_delivery_active flags
        $response = $this->getJson('/api/v1/countries');
        $response->assertOk()
            ->assertJsonFragment([
                'code' => 'SA',
                'is_active' => true,
                'is_delivery_active' => false,
            ])
            ->assertJsonFragment([
                'code' => 'YE',
                'is_active' => true,
                'is_delivery_active' => true,
            ]);

        // Delivery-only filter returns only YE
        $deliveryOnlyResponse = $this->getJson('/api/v1/countries?delivery_only=1');
        $deliveryOnlyResponse->assertOk();
        $codes = collect($deliveryOnlyResponse->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('YE'));
        $this->assertFalse($codes->contains('SA'));
    }

    public function test_customer_cannot_add_address_in_delivery_inactive_country(): void
    {
        $sa = Country::query()->create([
            'code' => 'SA',
            'name_ar' => 'السعودية',
            'calling_code' => '+966',
            'sort_order' => 2,
            'is_active' => true,
            'is_delivery_active' => false,
        ]);

        $saGov = Governorate::query()->create([
            'country_id' => $sa->id,
            'name_ar' => 'الرياض',
            'slug' => 'riyadh-test',
            'is_active' => true,
            'is_delivery_active' => true,
        ]);

        // Customer registered with Saudi phone number
        $customer = Customer::query()->create([
            'name' => 'عميل سعودي',
            'phone' => '501234567',
            'normalized_phone' => '+966501234567',
            'country_id' => $sa->id,
            'is_active' => true,
        ]);
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        // Attempting to add address in Saudi Arabia should fail validation because delivery is inactive
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل في السعودية',
                'country_id' => $sa->id,
                'governorate_id' => $saGov->id,
                'formatted_address' => 'شارع الملك فهد، الرياض',
                'latitude' => 24.7136,
                'longitude' => 46.6753,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_id']);
    }

    public function test_customer_from_saudi_can_add_delivery_address_to_active_yemen_governorate(): void
    {
        $ye = Country::query()->where('code', 'YE')->firstOrFail();
        $this->assertTrue($ye->is_delivery_active);

        $sanaa = Governorate::query()->where('country_id', $ye->id)->firstOrFail();
        $sanaa->update(['is_delivery_active' => true]);

        // Customer registered with Saudi phone number
        $customer = Customer::query()->create([
            'name' => 'عميل مقيم في السعودية',
            'phone' => '501234567',
            'normalized_phone' => '+966501234567',
            'country_id' => $ye->id,
            'is_active' => true,
        ]);
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        // Adding address in Yemen, Sana'a succeeds
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'الأهل في صنعاء',
                'country_id' => $ye->id,
                'governorate_id' => $sanaa->id,
                'formatted_address' => 'صنعاء، شارع حدة',
                'latitude' => 15.3694,
                'longitude' => 44.1910,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.country_id', $ye->id)
            ->assertJsonPath('data.governorate_id', $sanaa->id);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'country_id' => $ye->id,
            'governorate_id' => $sanaa->id,
            'label' => 'الأهل في صنعاء',
        ]);
    }

    public function test_customer_cannot_add_address_in_delivery_inactive_governorate(): void
    {
        $ye = Country::query()->where('code', 'YE')->firstOrFail();

        $inactiveGov = Governorate::query()->create([
            'country_id' => $ye->id,
            'name_ar' => 'محافظة خارج نطاق التوصيل',
            'slug' => 'out-of-delivery-gov',
            'is_active' => true,
            'is_delivery_active' => false,
        ]);

        $customer = Customer::query()->create([
            'name' => 'عميل اختبار',
            'phone' => '770001122',
            'normalized_phone' => '+967770001122',
            'country_id' => $ye->id,
            'is_active' => true,
        ]);
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'عنوان خارج التغطية',
                'country_id' => $ye->id,
                'governorate_id' => $inactiveGov->id,
                'formatted_address' => 'الشارع العام',
                'latitude' => 15.0,
                'longitude' => 44.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['governorate_id']);
    }
}
