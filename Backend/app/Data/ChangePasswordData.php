<?php

namespace App\Data;

final readonly class ChangePasswordData
{
    public function __construct(public string $currentPassword, public string $password) {}
}
