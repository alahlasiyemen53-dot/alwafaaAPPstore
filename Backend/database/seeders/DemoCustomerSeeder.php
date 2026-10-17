<?php

namespace Database\Seeders;

use App\Support\DemoCustomerOtp;
use Illuminate\Database\Seeder;

final class DemoCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customer = DemoCustomerOtp::ensureCustomer();

        if ($customer === null) {
            $this->command?->warn('Demo customer phone is invalid; skipping DemoCustomerSeeder.');

            return;
        }

        $this->command?->info(
            "Demo customer ready: {$customer->name} / ".DemoCustomerOtp::phone().' / OTP '.DemoCustomerOtp::code()
        );
    }
}
