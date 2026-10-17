<?php

namespace App\Enums;

enum ApiErrorCode: string
{
    case AuthRequired = 'AUTH_REQUIRED';
    case InvalidToken = 'INVALID_TOKEN';
    case TokenExpired = 'TOKEN_EXPIRED';
    case Forbidden = 'FORBIDDEN';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case ValidationFailed = 'VALIDATION_FAILED';
    case RateLimited = 'RATE_LIMITED';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case MalformedRequest = 'MALFORMED_REQUEST';
    case UnsupportedMediaType = 'UNSUPPORTED_MEDIA_TYPE';
    case NotAcceptable = 'NOT_ACCEPTABLE';
    case Conflict = 'CONFLICT';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case InternalError = 'INTERNAL_ERROR';

    // Sprint 23 Customer Auth & OTP Error Codes
    case OtpInvalid = 'OTP_INVALID';
    case OtpExpired = 'OTP_EXPIRED';
    case OtpAttemptsExhausted = 'OTP_ATTEMPTS_EXHAUSTED';
    case OtpRateLimited = 'OTP_RATE_LIMITED';
    case OtpChallengeInvalid = 'OTP_CHALLENGE_INVALID';
    case AuthenticationFailed = 'AUTHENTICATION_FAILED';
    case CustomerAccessRestricted = 'CUSTOMER_ACCESS_RESTRICTED';
    case Unauthenticated = 'UNAUTHENTICATED';

    // Sprint 24 Catalog Error Codes
    case ProductNotFound = 'PRODUCT_NOT_FOUND';
    case CategoryNotFound = 'CATEGORY_NOT_FOUND';
    case InvalidFilter = 'INVALID_FILTER';
    case InvalidSort = 'INVALID_SORT';

    // Sprint 28 Cart & Coupon Error Codes
    case CartItemNotFound = 'CART_ITEM_NOT_FOUND';
    case ProductVariantNotFound = 'PRODUCT_VARIANT_NOT_FOUND';
    case ProductVariantUnavailable = 'PRODUCT_VARIANT_UNAVAILABLE';
    case InvalidQuantity = 'INVALID_QUANTITY';
    case CouponInvalid = 'COUPON_INVALID';
    case CouponNotEligible = 'COUPON_NOT_ELIGIBLE';
    case CouponExpired = 'COUPON_EXPIRED';

    // Sprint 30 Devices Error Codes
    case DeviceNotFound = 'DEVICE_NOT_FOUND';

    // Sprint 31 Checkout & Orders Error Codes
    case CartEmpty = 'CART_EMPTY';
    case AddressNotFound = 'ADDRESS_NOT_FOUND';
    case GovernorateInactive = 'GOVERNORATE_INACTIVE';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case PaymentMethodUnsupported = 'PAYMENT_METHOD_UNSUPPORTED';
    case PaymentFailed = 'PAYMENT_FAILED';
    case ShippingMethodUnsupported = 'SHIPPING_METHOD_UNSUPPORTED';

    // Sprint 32 Customer Orders
    case OrderNotFound = 'ORDER_NOT_FOUND';

    // Review Comments & Admin Replies
    case ReviewNotFound = 'REVIEW_NOT_FOUND';
    case ReviewNotPublic = 'REVIEW_NOT_PUBLIC';
    case CommentNotFound = 'COMMENT_NOT_FOUND';
    case CommentNotAllowed = 'COMMENT_NOT_ALLOWED';
    case CommentNotOwned = 'COMMENT_NOT_OWNED';

    public function defaultMessage(): string
    {
        return match ($this) {
            self::AuthRequired => 'المصادقة مطلوبة للوصول إلى هذا المورد.',
            self::InvalidToken => 'رمز المصادقة غير صالح.',
            self::TokenExpired => 'انتهت صلاحية رمز المصادقة.',
            self::Forbidden => 'ليس لديك الصلاحية لتنفيذ هذا الإجراء.',
            self::ResourceNotFound => 'المورد المطلوب غير موجود.',
            self::ValidationFailed => 'بيانات الإدخال غير صالحة. يرجى مراجعة الحقول المحددة.',
            self::RateLimited => 'تجاوزت الحد المسموح به من الطلبات. يرجى الانتظار والمحاولة لاحقاً.',
            self::MethodNotAllowed => 'طريقة الطلب غير مدعومة لهذا المسار.',
            self::MalformedRequest => 'صيغة الطلب غير صالحة (Malformed JSON).',
            self::UnsupportedMediaType => 'نوع محتوى الطلب غير مدعوم. يرجى استخدام application/json.',
            self::NotAcceptable => 'نوع الاستجابة المطلوب غير مدعوم.',
            self::Conflict => 'تعارض في حالة المورد الحالية.',
            self::IdempotencyConflict => 'طلب متزامن بنفس مفتاح التكرار قيد المعالجة حالياً.',
            self::ServiceUnavailable => 'الخدمة غير متوفرة حالياً. يرجى المحاولة لاحقاً.',
            self::InternalError => 'حدث خطأ داخلي في الخادم.',

            // Sprint 23 Auth & OTP Messages
            self::OtpInvalid => 'رمز التحقق غير صحيح.',
            self::OtpExpired => 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد.',
            self::OtpAttemptsExhausted => 'تم استنفاد الحد الأقصى من محاولات إدخال الرمز.',
            self::OtpRateLimited => 'يرجى الانتظار قبل طلب رمز تحقق جديد.',
            self::OtpChallengeInvalid => 'طلب التحقق غير صالح أو لا يتطابق مع البيانات المدخلة.',
            self::AuthenticationFailed => 'فشلت عملية المصادقة. يرجى التحقق من صحة البيانات.',
            self::CustomerAccessRestricted => 'تم تقييد أو إيقاف هذا الحساب. يرجى التواصل مع الدعم الفني.',
            self::Unauthenticated => 'المستخدم غير مسجل الدخول.',

            // Sprint 24 Catalog Messages
            self::ProductNotFound => 'المنتج المطلوب غير متوفر أو غير موجود.',
            self::CategoryNotFound => 'القسم المطلوب غير متوفر أو غير موجود.',
            self::InvalidFilter => 'معيار التصفية غير صالح.',
            self::InvalidSort => 'معيار الترتيب غير صالح.',

            // Sprint 28 Cart & Coupon Messages
            self::CartItemNotFound => 'عنصر السلة غير موجود.',
            self::ProductVariantNotFound => 'نوع المنتج المحدد غير موجود.',
            self::ProductVariantUnavailable => 'نوع المنتج المحدد غير متوفر حالياً.',
            self::InvalidQuantity => 'الكمية المحددة غير صالحة.',
            self::CouponInvalid => 'كود الكوبون غير صالح.',
            self::CouponNotEligible => 'شروط استخدام هذا الكوبون غير مستوفاة.',
            self::CouponExpired => 'عذراً، انتهت صلاحية هذا الكوبون.',

            // Sprint 30 Devices Messages
            self::DeviceNotFound => 'الجهاز المطلوب غير موجود.',

            // Sprint 31 Checkout & Orders Messages
            self::CartEmpty => 'سلة المشتريات فارغة.',
            self::AddressNotFound => 'عنوان التوصيل غير موجود أو لا ينتمي لهذا الحساب.',
            self::GovernorateInactive => 'خدمة التوصيل غير متوفرة حالياً في هذه المحافظة.',
            self::InsufficientStock => 'الكمية المتوفرة في المخزون غير كافية لبعض المنتجات.',
            self::PaymentMethodUnsupported => 'طريقة الدفع المحددة غير مدعومة حالياً.',
            self::PaymentFailed => 'فشلت عملية الدفع. يرجى التحقق من البيانات والمحاولة مرة أخرى.',
            self::ShippingMethodUnsupported => 'طريقة التوصيل المحددة غير مدعومة.',
            self::OrderNotFound => 'الطلب المطلوب غير موجود.',

            // Review Comments
            self::ReviewNotFound => 'التقييم المطلوب غير موجود.',
            self::ReviewNotPublic => 'التقييم غير متاح للعرض العام.',
            self::CommentNotFound => 'التعليق المطلوب غير موجود.',
            self::CommentNotAllowed => 'غير مسموح بإضافة تعليق على هذا التقييم.',
            self::CommentNotOwned => 'لا يمكنك التعديل على تعليق لا تملكه.',
        };
    }
}
