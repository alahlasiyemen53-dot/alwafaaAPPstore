<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerAddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private Country $yemen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
        $this->yemen = Country::query()->where('code', 'YE')->firstOrFail();
    }

    public function test_can_create_customer_address_and_first_is_default(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل العناوين',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $governorate = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'slug' => 'sanaa-admin-test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/addresses", [
            'label' => 'المنزل',
            'contact_name' => 'محمد أحمد',
            'contact_phone' => '771234567',
            'country_id' => $this->yemen->id,
            'governorate_id' => $governorate->id,
            'neighborhood' => 'حدة',
            'street' => 'شارع الستين',
            'building' => '12',
            'is_default' => '0', // Even if 0 passed, first address must become default!
        ]);

        $response->assertRedirect("/admin/customers/{$customer->id}");

        $address = $customer->addresses()->firstOrFail();
        $this->assertSame('المنزل', $address->label);
        $this->assertTrue($address->is_default);
        $this->assertSame($this->yemen->id, $address->country_id);
        $this->assertSame($governorate->id, $address->governorate_id);
    }

    public function test_can_switch_default_address_atomically(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل العناوين',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $addr1 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المنزل',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'العمل',
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->patch("/admin/customers/{$customer->id}/addresses/{$addr2->id}/default");
        $response->assertRedirect();

        $this->assertFalse($addr1->fresh()->is_default);
        $this->assertTrue($addr2->fresh()->is_default);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_deleting_default_address_reassigns_fallback_default(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل العناوين',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $addr1 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'المنزل',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'العمل',
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/customers/{$customer->id}/addresses/{$addr1->id}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $addr1->id]);
        $this->assertTrue($addr2->fresh()->is_default);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_idor_protection_prevents_modifying_another_customer_address(): void
    {
        $admin = $this->userWithRole('admin');

        $customer1 = Customer::create([
            'name' => 'العميل الأول',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
        ]);

        $customer2 = Customer::create([
            'name' => 'العميل الثاني',
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
        ]);

        $governorate = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'slug' => 'sanaa-idor',
            'is_active' => true,
        ]);

        $addrOfCustomer2 = CustomerAddress::create([
            'customer_id' => $customer2->id,
            'label' => 'عنوان العميل الثاني',
            'country_id' => $this->yemen->id,
            'governorate_id' => $governorate->id,
            'is_default' => true,
        ]);

        $updatePayload = [
            'label' => 'محاولة اختراق',
            'country_id' => $this->yemen->id,
            'governorate_id' => $governorate->id,
        ];

        // Attempting to update customer2's address via customer1's route
        $response = $this->actingAs($admin)->put("/admin/customers/{$customer1->id}/addresses/{$addrOfCustomer2->id}", $updatePayload);
        $response->assertNotFound();

        // Attempting to delete customer2's address via customer1's route
        $deleteResponse = $this->actingAs($admin)->delete("/admin/customers/{$customer1->id}/addresses/{$addrOfCustomer2->id}");
        $deleteResponse->assertNotFound();

        // Attempting to set-default customer2's address via customer1's route
        $defaultResponse = $this->actingAs($admin)->patch("/admin/customers/{$customer1->id}/addresses/{$addrOfCustomer2->id}/default");
        $defaultResponse->assertNotFound();
    }

    public function test_admin_address_requires_country_and_governorate_belonging_to_country(): void
    {
        $admin = $this->userWithRole('admin');

        $otherCountry = Country::create([
            'code' => 'SA',
            'name_ar' => 'السعودية',
            'name_en' => 'Saudi Arabia',
            'calling_code' => '+966',
            'is_active' => true,
        ]);

        $govSanaa = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'صنعاء',
            'slug' => 'sanaa-geo',
            'is_active' => true,
        ]);

        $govRiyadh = Governorate::create([
            'country_id' => $otherCountry->id,
            'name_ar' => 'الرياض',
            'slug' => 'riyadh-geo',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل الجغرافيا',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $validResponse = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/addresses", [
            'label' => 'عنوان سليم',
            'country_id' => $this->yemen->id,
            'governorate_id' => $govSanaa->id,
        ]);
        $validResponse->assertRedirect();
        $this->assertSame(1, $customer->addresses()->count());

        $invalidResponse = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/addresses", [
            'label' => 'عنوان خاطئ',
            'country_id' => $this->yemen->id,
            'governorate_id' => $govRiyadh->id,
        ]);
        $invalidResponse->assertSessionHasErrors(['governorate_id']);
    }

    public function test_can_create_and_update_customer_address_with_city_and_district(): void
    {
        $admin = $this->userWithRole('admin');

        $customer = Customer::create([
            'name' => 'عميل مع مدينة ومديرية',
            'phone' => '773333333',
            'normalized_phone' => '+967773333333',
        ]);

        $governorate = Governorate::create([
            'country_id' => $this->yemen->id,
            'name_ar' => 'أمانة العاصمة',
            'slug' => 'capital-sanaa',
            'is_active' => true,
        ]);

        $city = City::create([
            'governorate_id' => $governorate->id,
            'name_ar' => 'صنعاء القديمة',
            'slug' => 'old-sanaa',
            'is_active' => true,
        ]);

        $district = District::create([
            'city_id' => $city->id,
            'name_ar' => 'حي السبعين',
            'slug' => 'sabeen-district',
            'is_active' => true,
        ]);

        // Create with city and district
        $createResponse = $this->actingAs($admin)->post("/admin/customers/{$customer->id}/addresses", [
            'label' => 'المنزل التراثي',
            'country_id' => $this->yemen->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'neighborhood' => 'حي القاسمي',
            'is_default' => '1',
        ]);

        $createResponse->assertRedirect();

        $address = $customer->addresses()->firstOrFail();
        $this->assertSame($city->id, $address->city_id);
        $this->assertSame($district->id, $address->district_id);
        $this->assertSame('صنعاء القديمة', $address->city->name_ar);
        $this->assertSame('حي السبعين', $address->district->name_ar);

        // Update address with new label and retain / update city and district
        $updateResponse = $this->actingAs($admin)->put("/admin/customers/{$customer->id}/addresses/{$address->id}", [
            'label' => 'المنزل المحدث',
            'country_id' => $this->yemen->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'neighborhood' => 'حي الفليحي',
            'is_default' => '1',
        ]);

        $updateResponse->assertRedirect();

        $address->refresh();
        $this->assertSame('المنزل المحدث', $address->label);
        $this->assertSame($city->id, $address->city_id);
        $this->assertSame($district->id, $address->district_id);
        $this->assertSame('حي الفليحي', $address->neighborhood);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([Role::query()->where('name', $role)->firstOrFail()]);

        return $user;
    }
}
