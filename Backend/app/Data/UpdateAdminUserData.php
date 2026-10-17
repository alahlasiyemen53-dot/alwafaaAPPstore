<?php

namespace App\Data;

final readonly class UpdateAdminUserData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public bool $isActive,
        public array $roles,
    ) {}
}
