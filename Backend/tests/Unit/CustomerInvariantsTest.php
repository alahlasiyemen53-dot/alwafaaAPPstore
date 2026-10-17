<?php

namespace Tests\Unit;

use App\Actions\CreateCustomerAddressAction;
use App\Actions\DeleteCustomerAddressAction;
use App\Actions\SetDefaultCustomerAddressAction;
use App\Actions\VerifyCustomerPhoneAction;
use App\Data\CustomerAddressData;
use App\Enums\PhoneVerificationMethod;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class CustomerInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_verification_requires_admin_user(): void
    {
        $action = app(VerifyCustomerPhoneAction::class);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Admin verification requires an admin user.');

        $action->execute($customer, PhoneVerificationMethod::Admin, null);
    }

    public function test_otp_verification_requires_verified_by_to_be_null(): void
    {
        $action = app(VerifyCustomerPhoneAction::class);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('OTP verification must not have a verifying admin user.');

        $action->execute($customer, PhoneVerificationMethod::Otp, $user);
    }

    public function test_customer_lifecycle_is_independent_from_verification(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
            'phone_verified_at' => null,
            'phone_verification_method' => null,
        ]);

        // Can be active + unverified
        $this->assertTrue($customer->is_active);
        $this->assertFalse($customer->isPhoneVerified());

        // Deactivating customer does not affect verification status
        $customer->update(['is_active' => false]);
        $this->assertFalse($customer->is_active);
        $this->assertFalse($customer->isPhoneVerified());

        // Verifying does not alter active flag
        $user = User::factory()->create();
        $action = app(VerifyCustomerPhoneAction::class);
        $action->execute($customer, PhoneVerificationMethod::Admin, $user);

        $customer->refresh();
        $this->assertFalse($customer->is_active); // Still inactive
        $this->assertTrue($customer->isPhoneVerified()); // Verified
    }

    public function test_first_address_becomes_default_automatically(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $action = app(CreateCustomerAddressAction::class);

        $addressData = new CustomerAddressData(
            label: 'Home',
            contactName: null,
            contactPhone: null,
            countryId: null,
            governorateId: null,
            neighborhood: null,
            street: null,
            building: null,
            floor: null,
            apartment: null,
            landmark: null,
            notes: null,
            latitude: null,
            longitude: null,
            googlePlaceId: null,
            formattedAddress: null,
            isDefault: false // explicitly requested false
        );

        // Invariant: First address must become default regardless
        $address = $action->execute($customer, $addressData);

        $this->assertTrue($address->is_default);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_setting_another_default_address_unsets_old_default(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $addr1 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Address 1',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Address 2',
            'is_default' => false,
        ]);

        $setDefaultAction = app(SetDefaultCustomerAddressAction::class);
        $setDefaultAction->execute($addr2);

        $this->assertFalse($addr1->fresh()->is_default);
        $this->assertTrue($addr2->fresh()->is_default);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }

    public function test_deleting_default_address_reassigns_fallback_default(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
        ]);

        $addr1 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Address 1',
            'is_default' => true,
        ]);

        $addr2 = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Address 2',
            'is_default' => false,
        ]);

        $deleteAction = app(DeleteCustomerAddressAction::class);
        $deleteAction->execute($addr1);

        $this->assertDatabaseMissing('customer_addresses', ['id' => $addr1->id]);
        $this->assertTrue($addr2->fresh()->is_default);
        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
    }
}
