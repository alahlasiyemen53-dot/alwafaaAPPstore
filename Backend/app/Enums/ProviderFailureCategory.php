<?php

namespace App\Enums;

enum ProviderFailureCategory: string
{
    case Success = 'success';
    case PermanentTokenFailure = 'permanent_token_failure';
    case TransientProviderFailure = 'transient_provider_failure';
    case InvalidPayload = 'invalid_payload';
    case AuthConfigurationFailure = 'auth_configuration_failure';
    case UnknownFailure = 'unknown_failure';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'نجاح',
            self::PermanentTokenFailure => 'رمز الجهاز مرفوض نهائياً',
            self::TransientProviderFailure => 'فشل مؤقت من مزود الإشعارات',
            self::InvalidPayload => 'حمولة الإشعار غير صالحة',
            self::AuthConfigurationFailure => 'فشل إعدادات مصادقة FCM',
            self::UnknownFailure => 'فشل غير معروف',
        };
    }
}
