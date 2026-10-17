<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof User) {
            return [];
        }

        $user = $this->resource;
        $lastLoginAt = $user->getAttribute('last_login_at');
        $createdAt = $user->getAttribute('created_at');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'last_login_at' => $lastLoginAt instanceof \DateTimeInterface ? $lastLoginAt->format(DATE_ATOM) : null,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $createdAt instanceof \DateTimeInterface ? $createdAt->format(DATE_ATOM) : null,
        ];
    }
}
