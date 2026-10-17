<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Devices;

use App\Models\PushDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PushDevice
 */
final class PushDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $platform = is_object($this->platform) && property_exists($this->platform, 'value')
            ? $this->platform->value
            : (string) $this->platform;

        $status = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'platform' => $platform,
            'device_identifier' => $this->device_identifier,
            'locale' => $this->locale,
            'app_version' => $this->app_version,
            'status' => $status,
            'last_registered_at' => $this->last_registered_at?->toIso8601String(),
        ];
    }
}
