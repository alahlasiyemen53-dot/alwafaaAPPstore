<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AdminModerateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.reviews.moderate') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('action') && $this->has('status')) {
            $mappedAction = match ($this->input('status')) {
                'approved' => 'approve',
                'rejected' => 'reject',
                'hidden' => 'hide',
                'pending' => 'restore',
                default => (string) $this->input('status'),
            };
            $this->merge(['action' => $mappedAction]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject,hide,restore,pending'],
            'reason' => ['required_if:action,reject,hide', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'سبب الإشراف إلزامي عند رفض أو إخفاء التقييم.',
        ];
    }
}
