<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AssignUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['roles' => ['required', 'array', 'min:1'], 'roles.*' => ['string', 'distinct', 'exists:roles,name']];
    }
}
