<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['email' => 'alahlasiyemen53@gmail.com'],
            [
                'name' => 'مدير النظام',
                'phone' => null,
                'password' => Hash::make('admin@123'),
                'is_active' => true,
            ]
        );

        $superAdminRole = Role::firstWhere('name', 'super_admin');
        if ($superAdminRole !== null) {
            $superAdmin->syncRoles([$superAdminRole->id]);
        }
    }
}
