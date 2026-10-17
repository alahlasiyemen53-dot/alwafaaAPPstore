<?php

namespace App\Enums;

enum DeliveryFailureReason: string
{
    case CustomerUnavailable = 'customer_unavailable';
    case CustomerRefused = 'customer_refused';
    case WrongAddress = 'wrong_address';
    case PhoneUnreachable = 'phone_unreachable';
    case DeliveryIssue = 'delivery_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CustomerUnavailable => 'العميل غير متواجد في العنوان',
            self::CustomerRefused => 'العميل رفض استلام الطلب',
            self::WrongAddress => 'العنوان غير صحيح أو غير واضح',
            self::PhoneUnreachable => 'هاتف العميل مغلق أو لا يرد',
            self::DeliveryIssue => 'تعذر الوصول بسبب ظروف الطريق/الطقس',
            self::Other => 'أسباب أخرى',
        };
    }
}
