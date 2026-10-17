<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Loyalty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CustomerLoyaltyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'available_points' => (int) ($this['available_points'] ?? 0),
            'lifetime_earned' => (int) ($this['lifetime_earned'] ?? 0),
            'lifetime_redeemed' => (int) ($this['lifetime_redeemed'] ?? 0),
            'current_tier' => $this['current_tier'] !== null ? [
                'id' => $this['current_tier']->id,
                'code' => $this['current_tier']->code,
                'name_ar' => $this['current_tier']->name_ar,
                'name_en' => $this['current_tier']->name_en,
            ] : null,
            'recent_entries' => array_map(function ($entry): array {
                $type = is_object($entry->type) && property_exists($entry->type, 'value')
                    ? $entry->type->value
                    : (string) $entry->type;

                return [
                    'id' => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'type' => $type,
                    'points' => (int) $entry->points,
                    'description' => $entry->description,
                    'occurred_at' => $entry->occurred_at?->toIso8601String(),
                ];
            }, $this['recent_entries'] ?? []),
        ];
    }
}
