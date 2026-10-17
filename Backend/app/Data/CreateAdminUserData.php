<?php

namespace App\Data;

final readonly class CreateAdminUserData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $password,
        public bool $isActive,
        public array $roles,
    ) {}
}
