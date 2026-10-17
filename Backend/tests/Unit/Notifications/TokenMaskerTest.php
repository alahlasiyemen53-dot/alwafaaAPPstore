<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\TokenMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TokenMaskerTest extends TestCase
{
    #[DataProvider('maskedValues')]
    public function test_null_and_short_tokens_use_the_fixed_safe_mask(?string $token): void
    {
        self::assertSame('••••••••', app(TokenMasker::class)->mask($token));
    }

    public function test_long_tokens_reveal_only_a_prefix_and_suffix(): void
    {
        $token = 'long-fcm-registration-token';
        $maskedToken = app(TokenMasker::class)->mask($token);

        self::assertSame('long…oken', $maskedToken);
        self::assertNotSame($token, $maskedToken);
    }

    /** @return array<string, array{?string}> */
    public static function maskedValues(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'short' => ['short-token'],
            'boundary' => ['twelve-token'],
        ];
    }
}
