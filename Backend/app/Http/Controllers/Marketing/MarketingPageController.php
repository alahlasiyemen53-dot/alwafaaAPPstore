<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\CustomerSupportService;
use Illuminate\View\View;

final class MarketingPageController extends Controller
{
    public function __construct(
        private readonly CustomerSupportService $supportService,
    ) {}

    public function home(): View
    {
        return view('marketing.home', [
            'title' => 'الأناقة والجمال',
            'metaDescription' => 'متجر رقمي لمستحضرات التجميل والعطور المختارة — اكتشف، اطلب، واستلم بأناقة.',
        ]);
    }

    public function about(): View
    {
        return view('marketing.about', [
            'title' => 'من نحن',
            'metaDescription' => 'تعرف على قصة الأناقة والجمال ورؤيتنا لتجربة تسوّق تجميل رقمية منظمة.',
        ]);
    }

    public function contact(): View
    {
        try {
            $settings = $this->supportService->getSettings();
        } catch (\Throwable) {
            $settings = [
                'phone_enabled' => false,
                'phone' => null,
                'tel_link' => null,
                'whatsapp_enabled' => false,
                'whatsapp_number' => null,
                'whatsapp_deep_link' => null,
                'hours_text_ar' => null,
            ];
        }

        $phoneEnabled = (bool) ($settings['phone_enabled'] ?? false);
        $phone = $phoneEnabled ? ($settings['phone'] ?? null) : null;
        $telLink = $phoneEnabled ? ($settings['tel_link'] ?? null) : null;

        $whatsappEnabled = (bool) ($settings['whatsapp_enabled'] ?? false);
        $whatsappNumber = $whatsappEnabled ? ($settings['whatsapp_number'] ?? null) : null;
        $whatsappLink = $whatsappEnabled ? ($settings['whatsapp_deep_link'] ?? null) : null;

        return view('marketing.contact', [
            'title' => 'تواصل معنا',
            'metaDescription' => 'قنوات التواصل مع فريق الأناقة والجمال — هاتف، واتساب، وبريد.',
            'phone' => $phone,
            'telLink' => $telLink,
            'whatsappNumber' => $whatsappNumber,
            'whatsappLink' => $whatsappLink,
            'hoursText' => $settings['hours_text_ar'] ?? null,
            'fallbackEmail' => 'hello@beauty-elegance.app',
        ]);
    }

    public function privacy(): View
    {
        return view('marketing.privacy', [
            'title' => 'سياسة الخصوصية',
            'metaDescription' => 'كيف تجمع منصة الأناقة والجمال بياناتك وتحميها وتستخدمها في تجربة التسوّق والطلبات.',
        ]);
    }

    public function deleteAccount(): View
    {
        return view('marketing.delete-account', [
            'title' => 'تعليمات حذف الحساب',
            'metaDescription' => 'خطوات طلب حذف حسابك في تطبيق الأناقة والجمال وفق سياسات المتاجر.',
        ]);
    }
}
