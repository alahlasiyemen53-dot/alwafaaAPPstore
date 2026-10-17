<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AuthorizationSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(MasterDataSeeder::class);
        $this->call(ArabCountriesAndGovernoratesSeeder::class);
        $this->call(DeliveryMethodSeeder::class);
        $this->call(CustomerPaymentMethodSeeder::class);
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(AccountingPeriodSeeder::class);
        $this->call(AccountingMappingSeeder::class);
        $this->call(DemoCustomerSeeder::class);
        $this->call(DemoOrdersSeeder::class);
        $this->call(HomeSectionSeeder::class);
    }
}
