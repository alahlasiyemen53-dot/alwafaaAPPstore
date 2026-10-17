<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class MarketingPagesTest extends TestCase
{
    public function test_marketing_pages_are_publicly_reachable(): void
    {
        $this->get(route('marketing.home'))->assertOk()->assertSee('الوفاء استور', false);
        $this->get(route('marketing.about'))->assertOk()->assertSee('من نحن', false);
        $this->get(route('marketing.contact'))->assertOk()->assertSee('تواصل معنا', false);
        $this->get(route('marketing.privacy'))->assertOk()->assertSee('سياسة الخصوصية', false);
        $this->get(route('marketing.delete-account'))->assertOk()->assertSee('تعليمات حذف الحساب', false);
    }
}
