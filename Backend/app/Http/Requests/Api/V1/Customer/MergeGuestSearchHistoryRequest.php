<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

final class MergeGuestSearchHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'queries' => ['required', 'array', 'max:15'],
            'queries.*' => ['string', 'min:2', 'max:191'],
        ];
    }
}
