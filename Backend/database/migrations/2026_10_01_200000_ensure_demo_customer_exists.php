<?php

use App\Support\DemoCustomerOtp;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! DemoCustomerOtp::enabled()) {
            return;
        }

        DemoCustomerOtp::ensureCustomer();
    }

    public function down(): void
    {
        $normalized = DemoCustomerOtp::normalizedPhone();
        if ($normalized === null) {
            return;
        }

        \App\Models\Customer::query()
            ->where('normalized_phone', $normalized)
            ->where('notes', 'like', 'حساب تجريبي للاختبار%')
            ->delete();
    }
};
