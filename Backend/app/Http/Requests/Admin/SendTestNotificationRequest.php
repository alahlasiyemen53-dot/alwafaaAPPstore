<?php

namespace App\Http\Requests\Admin;

use App\Enums\PushDeviceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendTestNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.notifications.send_test') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'push_device_id' => [
                'required',
                'integer',
                Rule::exists('push_devices', 'id')->where('status', PushDeviceStatus::Active->value),
            ],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
        ];
    }
}
