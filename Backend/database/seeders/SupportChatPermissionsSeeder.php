<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class SupportChatPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AuthorizationSeeder::class);
    }
}
