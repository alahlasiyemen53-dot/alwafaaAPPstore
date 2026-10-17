<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomeSectionType;
use Illuminate\Database\Eloquent\Model;

final class HomeSection extends Model
{
    protected $fillable = [
        'type',
        'title_ar',
        'title_en',
        'is_active',
        'sort_order',
        'configuration',
    ];

    protected $casts = [
        'type' => HomeSectionType::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'configuration' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
