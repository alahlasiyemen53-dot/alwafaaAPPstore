<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SupportChat;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required_without:image', 'nullable', 'string', 'max:4000'],
            'client_message_id' => ['nullable', 'string', 'max:64'],
            'image' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
