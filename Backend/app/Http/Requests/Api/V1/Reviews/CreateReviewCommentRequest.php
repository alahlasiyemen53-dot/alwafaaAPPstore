<?php

namespace App\Http\Requests\Api\V1\Reviews;

use Illuminate\Foundation\Http\FormRequest;

final class CreateReviewCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer_jwt') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'نص التعليق مطلوب.',
            'body.max' => 'نص التعليق يجب ألا يتجاوز 1000 حرف.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body') && is_string($this->input('body'))) {
            $this->merge([
                'body' => trim(strip_tags($this->input('body'))),
            ]);
        }
    }
}
