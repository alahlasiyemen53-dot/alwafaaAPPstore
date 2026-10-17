<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the initial super_admin account securely';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = trim((string) $this->ask('Email (optional)')) ?: null;
        $phone = trim((string) $this->ask('Phone (optional)')) ?: null;
        $password = (string) $this->secret('Password (minimum 12 characters)');

        if ($name === '' || strlen($password) < 12 || ($email === null && $phone === null)) {
            $this->error('Name, a phone or email, and a 12-character password are required.');

            return self::FAILURE;
        }

        if (User::query()->where(fn ($query) => $query->when($email, fn ($q) => $q->orWhere('email', $email))->when($phone, fn ($q) => $q->orWhere('phone', $phone)))->exists()) {
            $this->error('A user with that identifier already exists.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $phone, $password): void {
            $user = User::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => Hash::make($password), 'is_active' => true]);
            $user->syncRoles([Role::query()->where('name', 'super_admin')->firstOrFail()]);
        });

        $this->info('Super admin created.');

        return self::SUCCESS;
    }
}
