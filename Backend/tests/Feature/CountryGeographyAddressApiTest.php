<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Governorate;
use App\Services\Auth\CustomerJwtService;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CountryGeographyAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private Country $yemen;

    private Governorate $governorate;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['*/health' => Http::response(['status' => 'unavailable'], 503)]);

        $this->yemen = Country::query()->where('code', 'YE')->firstOrFail();

        Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->governorate = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'name_en' => "Sana'a",
            'slug' => 'sanaa-test',
            'is_active' => true,
        ]);
    }

    public function test_yemen_country_is_seeded(): void
    {
        $this->assertSame('YE', $this->yemen->code);
        $this->assertSame('+967', $this->yemen->calling_code);
    }

    public function test_countries_and_governorates_by_country_endpoints(): void
    {
        $this->getJson('/api/v1/countries')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'YE');

        $this->getJson('/api/v1/countries/'.$this->yemen->id.'/governorates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->governorate->id);
    }

    public function test_legacy_city_and_district_endpoints_return_empty_lists(): void
    {
        $this->getJson('/api/v1/governorates/'.$this->governorate->id.'/cities')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/cities/1/districts')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_address_can_be_created_without_city_or_district(): void
    {
        $customer = $this->createCustomer('771234567');
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'country_id' => $this->yemen->id,
                'governorate_id' => $this->governorate->id,
                'formatted_address' => 'حي حدة، شارع رئيسي',
                'latitude' => 15.369445,
                'longitude' => 44.191006,
            ])
            ->assertCreated()
            ->assertJsonPath('data.country.code', 'YE')
            ->assertJsonPath('data.governorate.id', $this->governorate->id)
            ->assertJsonMissingPath('data.city')
            ->assertJsonMissingPath('data.district');
    }

    public function test_address_rejects_missing_governorate(): void
    {
        $customer = $this->createCustomer('771234568');
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'المنزل',
                'country_id' => $this->yemen->id,
                'formatted_address' => 'عنوان بدون محافظة',
                'latitude' => 15.369445,
                'longitude' => 44.191006,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['governorate_id']);
    }

    public function test_phone_normalization_uses_yemen_calling_code_from_country(): void
    {
        $this->assertSame('+967771234569', PhoneNormalizer::normalize('771234569', '+967'));
    }

    public function test_otp_request_accepts_explicit_country_id(): void
    {
        $this->postJson('/api/v1/auth/otp/request', [
            'country_id' => $this->yemen->id,
            'phone' => '771234570',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['data' => ['challenge_id']]);

        $this->assertDatabaseHas('customer_otp_challenges', [
            'normalized_phone' => '+967771234570',
        ]);
    }

    public function test_address_accepts_coordinates_without_service_area_polygon(): void
    {
        config()->set('delivery.sanaa_polygon', [[44.0, 15.0], [45.0, 15.0], [45.0, 16.0], [44.0, 16.0]]);

        $customer = $this->createCustomer('771234571');
        $token = app(CustomerJwtService::class)->generateAccessToken($customer);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/addresses', [
                'label' => 'أي موقع',
                'country_id' => $this->yemen->id,
                'governorate_id' => $this->governorate->id,
                'formatted_address' => 'موقع خارج المضلع السابق',
                'latitude' => 16.5,
                'longitude' => 44.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.latitude', 16.5)
            ->assertJsonPath('data.longitude', 44.5);
    }

    private function createCustomer(string $localPhone): Customer
    {
        return Customer::create([
            'name' => 'عميل اختبار',
            'phone' => $localPhone,
            'normalized_phone' => '+967'.$localPhone,
            'email' => "test_{$localPhone}@example.com",
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'otp',
        ]);
    }
}
