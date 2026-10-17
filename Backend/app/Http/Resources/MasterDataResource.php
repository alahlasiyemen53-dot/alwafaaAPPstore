<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MasterDataResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $model = $this->resource;
        if (! $model instanceof Model) {
            return [];
        }

        return $model->toArray();
    }
}
