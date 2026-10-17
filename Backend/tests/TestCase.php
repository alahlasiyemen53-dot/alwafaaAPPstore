<?php

namespace Tests;

use App\Contracts\Notifications\PushNotificationSender;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DeliveryMethod;
use App\Models\Governorate;
use App\Models\GovernorateDeliveryMethod;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\Notifications\FakePushNotificationSender;

abstract class TestCase extends BaseTestCase
{
    private static ?int $customerAddressGeographyHookAppId = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://localhost']);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $this->app->singleton(PushNotificationSender::class, FakePushNotificationSender::class);
        $this->registerCustomerAddressGeographyHook();
    }

    /**
     * Legacy feature fixtures often create addresses without geography.
     * Order creation now requires an active governorate, so fill a default
     * Yemen governorate only when governorate_id is omitted.
     */
    private function registerCustomerAddressGeographyHook(): void
    {
        $appId = spl_object_id($this->app);
        if (self::$customerAddressGeographyHookAppId === $appId) {
            return;
        }

        self::$customerAddressGeographyHookAppId = $appId;

        CustomerAddress::creating(function (CustomerAddress $address): void {
            if ($address->governorate_id !== null) {
                $this->ensureStandardDeliveryAssignment((int) $address->governorate_id);

                return;
            }

            $country = Country::query()->firstOrCreate(
                ['code' => 'YE'],
                [
                    'name_ar' => 'اليمن',
                    'name_en' => 'Yemen',
                    'calling_code' => '+967',
                    'is_active' => true,
                ]
            );

            // Zero-fee fixture so legacy order tests that ignore delivery stay stable.
            $governorate = Governorate::query()->firstOrCreate(
                ['slug' => 'test-default-zero-delivery-fee'],
                [
                    'country_id' => $country->id,
                    'name_ar' => 'محافظة اختبار',
                    'name_en' => 'Test Governorate',
                    'is_active' => true,
                ]
            );

            if (! $governorate->is_active) {
                $governorate->forceFill([
                    'country_id' => $country->id,
                    'is_active' => true,
                ])->save();
            }

            $address->governorate_id = $governorate->id;
            if ($address->country_id === null) {
                $address->country_id = $country->id;
            }

            $this->ensureStandardDeliveryAssignment($governorate->id);
        });
    }

    /**
     * Ensure a standard shipping assignment exists for fixtures.
     * When $deliveryFee is provided, update the assignment fee if it was just created
     * or leave existing explicit fees alone when null (defaults to 0 for new rows).
     */
    protected function ensureStandardDeliveryAssignment(int $governorateId, ?float $deliveryFee = null): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('delivery_methods')
            || ! \Illuminate\Support\Facades\Schema::hasTable('governorate_delivery_methods')) {
            return;
        }

        $method = DeliveryMethod::query()->firstOrCreate(
            ['code' => 'standard'],
            [
                'name_ar' => 'توصيل قياسي',
                'name_en' => 'Standard Delivery',
                'price' => 0,
                'estimated_delivery_ar' => 'خلال 24-48 ساعة',
                'estimated_delivery_en' => 'Within 24-48 hours',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $assignment = GovernorateDeliveryMethod::query()->firstOrCreate(
            [
                'governorate_id' => $governorateId,
                'delivery_method_id' => $method->id,
            ],
            [
                'delivery_fee' => $deliveryFee ?? 0,
                'is_active' => true,
                'sort_order' => (int) $method->sort_order,
            ]
        );

        if ($deliveryFee !== null && (float) $assignment->delivery_fee !== $deliveryFee) {
            $assignment->forceFill(['delivery_fee' => $deliveryFee, 'is_active' => true])->save();
        }
    }
}
