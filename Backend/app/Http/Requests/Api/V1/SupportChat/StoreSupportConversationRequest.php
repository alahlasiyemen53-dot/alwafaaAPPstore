<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SupportChat;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'related_order_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
