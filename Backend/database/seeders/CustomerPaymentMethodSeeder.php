<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentChannel;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use App\Models\CustomerPaymentMethod;
use Illuminate\Database\Seeder;

final class CustomerPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'cod',
                'provider_code' => PaymentProviderCode::Cod,
                'name_ar' => 'الدفع عند الاستلام (COD)',
                'name_en' => 'Cash on Delivery',
                'description_ar' => 'الدفع نقداً عند استلام طلبك من مندوب التوصيل',
                'description_en' => 'Pay with cash upon delivery by the driver',
                'instructions_ar' => null,
                'instructions_en' => null,
                'channel' => PaymentChannel::Offline,
                'flow_type' => PaymentFlowType::None,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'jaib',
                'provider_code' => PaymentProviderCode::Jaib,
                'name_ar' => 'جيب',
                'name_en' => 'Jaib',
                'description_ar' => 'الدفع عبر محفظة جيب برمز الشراء',
                'description_en' => 'Pay via Jaib wallet purchase code',
                'instructions_ar' => 'أدخل رقم جوالك ورمز الشراء من تطبيق جيب',
                'instructions_en' => 'Enter your mobile and Jaib purchase code',
                'channel' => PaymentChannel::Online,
                'flow_type' => PaymentFlowType::Step1,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'floosak',
                'provider_code' => PaymentProviderCode::Floosak,
                'name_ar' => 'فلوسك',
                'name_en' => 'Floosak',
                'description_ar' => 'الدفع عبر محفظة فلوسك برمز تحقق OTP',
                'description_en' => 'Pay via Floosak wallet OTP confirmation',
                'instructions_ar' => 'سيتم إرسال رمز تحقق إلى جوالك لإتمام الدفع',
                'instructions_en' => 'An OTP will be sent to confirm payment',
                'channel' => PaymentChannel::Online,
                'flow_type' => PaymentFlowType::Step2,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            CustomerPaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
