<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['parent_id', 'name_ar', 'name_en', 'slug', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::updating(function (self $category): void {
            if ($category->isDirty('slug') && $category->getOriginal('slug') !== null) {
                $category->slug = $category->getOriginal('slug');
            }
        });
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('category_image')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->quality(75)
            ->width(150)
            ->height(150);

        $this->addMediaConversion('small')
            ->nonQueued()
            ->format('webp')
            ->quality(80)
            ->width(400);
    }

    public function getImageUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('category_image');

        return $media ? $media->getUrl($conversion) : null;
    }
}
