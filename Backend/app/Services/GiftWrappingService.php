<?php

namespace App\Services;

use App\Models\Setting;
use DomainException;
use Illuminate\Support\Facades\Cache;

final class GiftWrappingService
{
    private const CACHE_KEY = 'settings.gift_wrapping';
    public const DEFAULT_MAX_MESSAGE_LENGTH = 250;
    public const MIN_MESSAGE_LENGTH = 10;
    public const MAX_ALLOWED_MESSAGE_LENGTH = 1000;

    /**
     * @return array{
     *     enabled: bool,
     *     message_enabled: bool,
     *     max_length: int,
     *     is_free: bool
     * }
     */
    public function getSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $settings = Setting::query()
                ->whereIn('key', [
                    'gift_wrapping_enabled',
                    'gift_message_enabled',
                    'gift_message_max_length',
                ])
                ->pluck('value', 'key');

            $enabled = filter_var($settings['gift_wrapping_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $messageEnabled = $enabled && filter_var($settings['gift_message_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $maxLength = isset($settings['gift_message_max_length']) && is_numeric($settings['gift_message_max_length'])
                ? max(self::MIN_MESSAGE_LENGTH, min(self::MAX_ALLOWED_MESSAGE_LENGTH, (int) $settings['gift_message_max_length']))
                : self::DEFAULT_MAX_MESSAGE_LENGTH;

            return [
                'enabled' => $enabled,
                'message_enabled' => $messageEnabled,
                'max_length' => $maxLength,
                'is_free' => true,
            ];
        });
    }

    public function isEnabled(): bool
    {
        return $this->getSettings()['enabled'];
    }

    public function isMessageEnabled(): bool
    {
        return $this->getSettings()['message_enabled'];
    }

    public function getMaxMessageLength(): int
    {
        return $this->getSettings()['max_length'];
    }

    /**
     * Enforces the V1 business invariant: Gift Wrapping is strictly a FREE service.
     */
    public function isFree(): bool
    {
        return true;
    }

    /**
     * Validates and sanitizes a customer/admin provided gift message.
     *
     * @throws DomainException
     */
    public function validateAndCleanMessage(?string $message, bool $wrappingRequested): ?string
    {
        if (! $wrappingRequested) {
            return null;
        }

        if ($message === null || trim($message) === '') {
            return null;
        }

        if (! $this->isEnabled()) {
            throw new DomainException('خدمة تغليف الهدايا غير مفعّلة حالياً.');
        }

        if (! $this->isMessageEnabled()) {
            throw new DomainException('خدمة إرفاق بطاقة رسالة الهدية غير مفعّلة حالياً.');
        }

        // Plain text only: strip all HTML/JS tags, collapse carriage returns
        $cleaned = trim(strip_tags($message));

        $maxLength = $this->getMaxMessageLength();
        if (mb_strlen($cleaned) > $maxLength) {
            throw new DomainException("طول رسالة الهدية ({$maxLength} حرفاً كحد أقصى) يتجاوز الحد المسموح به.");
        }

        return $cleaned;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
