<?php

namespace App\Http\Resources;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Permission) {
            return [];
        }

        $permission = $this->resource;

        return ['id' => $permission->id, 'name' => $permission->name, 'display_name' => $permission->display_name, 'description' => $permission->description];
    }
}
