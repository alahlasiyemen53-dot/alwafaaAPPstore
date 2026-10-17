<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Support;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerSupportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $phoneEnabled = (bool) ($this['phone_enabled'] ?? false);
        $whatsappEnabled = (bool) ($this['whatsapp_enabled'] ?? false);

        return [
            'phone' => [
                'enabled' => $phoneEnabled,
                'number' => $phoneEnabled ? ($this['phone'] ?? null) : null,
                'tel_link' => $phoneEnabled ? ($this['tel_link'] ?? null) : null,
            ],
            'whatsapp' => [
                'enabled' => $whatsappEnabled,
                'number' => $whatsappEnabled ? ($this['whatsapp_number'] ?? null) : null,
                'deep_link' => $whatsappEnabled ? ($this['whatsapp_deep_link'] ?? null) : null,
                'default_message' => $whatsappEnabled ? ($this['whatsapp_default_message'] ?? null) : null,
            ],
            'hours_text_ar' => $this['hours_text_ar'] ?? null,
        ];
    }
}
