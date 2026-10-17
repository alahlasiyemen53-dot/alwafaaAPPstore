<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Cache;

final class CustomerSupportService
{
    private const CACHE_KEY = 'settings.customer_support';

    /**
     * @return array{
     *     phone_enabled: bool,
     *     phone: ?string,
     *     normalized_phone: ?string,
     *     tel_link: ?string,
     *     whatsapp_enabled: bool,
     *     whatsapp_number: ?string,
     *     normalized_whatsapp_digits: ?string,
     *     whatsapp_default_message: ?string,
     *     whatsapp_deep_link: ?string,
     *     hours_text_ar: ?string
     * }
     */
    public function getSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $settings = Setting::query()
                ->whereIn('key', [
                    'support_phone_enabled',
                    'support_phone',
                    'support_whatsapp_enabled',
                    'support_whatsapp_number',
                    'support_whatsapp_default_message',
                    'support_hours_text_ar',
                ])
                ->pluck('value', 'key');

            $phoneEnabled = filter_var($settings['support_phone_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $rawPhone = isset($settings['support_phone']) && trim((string) $settings['support_phone']) !== ''
                ? trim((string) $settings['support_phone'])
                : null;
            $normalizedPhone = $rawPhone !== null ? PhoneNormalizer::normalize($rawPhone) : null;
            $telLink = ($phoneEnabled && $normalizedPhone !== null) ? 'tel:'.$normalizedPhone : null;

            $whatsappEnabled = filter_var($settings['support_whatsapp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $rawWhatsapp = isset($settings['support_whatsapp_number']) && trim((string) $settings['support_whatsapp_number']) !== ''
                ? trim((string) $settings['support_whatsapp_number'])
                : null;
            $normalizedWhatsapp = $rawWhatsapp !== null ? PhoneNormalizer::normalize($rawWhatsapp) : null;
            $whatsappDigits = $normalizedWhatsapp !== null ? ltrim($normalizedWhatsapp, '+') : null;

            $whatsappMessage = isset($settings['support_whatsapp_default_message']) && trim((string) $settings['support_whatsapp_default_message']) !== ''
                ? trim(strip_tags((string) $settings['support_whatsapp_default_message']))
                : null;

            $whatsappDeepLink = null;
            if ($whatsappEnabled && $whatsappDigits !== null && $whatsappDigits !== '') {
                $whatsappDeepLink = 'https://wa.me/'.$whatsappDigits;
                if ($whatsappMessage !== null && $whatsappMessage !== '') {
                    $whatsappDeepLink .= '?text='.rawurlencode($whatsappMessage);
                }
            }

            $hoursTextAr = isset($settings['support_hours_text_ar']) && trim((string) $settings['support_hours_text_ar']) !== ''
                ? trim(strip_tags((string) $settings['support_hours_text_ar']))
                : null;

            return [
                'phone_enabled' => $phoneEnabled,
                'phone' => $rawPhone,
                'normalized_phone' => $normalizedPhone,
                'tel_link' => $telLink,
                'whatsapp_enabled' => $whatsappEnabled,
                'whatsapp_number' => $rawWhatsapp,
                'normalized_whatsapp_digits' => $whatsappDigits,
                'whatsapp_default_message' => $whatsappMessage,
                'whatsapp_deep_link' => $whatsappDeepLink,
                'hours_text_ar' => $hoursTextAr,
            ];
        });
    }

    public function isPhoneEnabled(): bool
    {
        return $this->getSettings()['phone_enabled'];
    }

    public function getPhone(): ?string
    {
        return $this->getSettings()['phone'];
    }

    public function getNormalizedPhone(): ?string
    {
        return $this->getSettings()['normalized_phone'];
    }

    public function getPhoneTelLink(): ?string
    {
        return $this->getSettings()['tel_link'];
    }

    public function isWhatsappEnabled(): bool
    {
        return $this->getSettings()['whatsapp_enabled'];
    }

    public function getWhatsappNumber(): ?string
    {
        return $this->getSettings()['whatsapp_number'];
    }

    public function getNormalizedWhatsappDigits(): ?string
    {
        return $this->getSettings()['normalized_whatsapp_digits'];
    }

    public function getWhatsappDefaultMessage(): ?string
    {
        return $this->getSettings()['whatsapp_default_message'];
    }

    public function getWhatsappDeepLink(): ?string
    {
        return $this->getSettings()['whatsapp_deep_link'];
    }

    public function getHoursText(): ?string
    {
        return $this->getSettings()['hours_text_ar'];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
