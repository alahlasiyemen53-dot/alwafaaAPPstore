<?php

namespace App\Services\Notifications;

final class TokenMasker
{
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function mask(?string $token): string
    {
        if ($token === null || mb_strlen($token) <= 12) {
            return '••••••••';
        }

        return mb_substr($token, 0, 4).'…'.mb_substr($token, -4);
    }
}
