<?php

namespace App\ValueObjects;

final class OtpSendResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly string $provider,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $provider, ?string $messageId = null): self
    {
        return new self(
            successful: true,
            provider: $provider,
            messageId: $messageId,
            error: null,
        );
    }

    public static function failure(string $provider, string $error): self
    {
        return new self(
            successful: false,
            provider: $provider,
            messageId: null,
            error: $error,
        );
    }
}
