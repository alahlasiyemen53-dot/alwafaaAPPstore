<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Brand extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['name_ar', 'name_en', 'slug', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::updating(function (self $brand): void {
            if ($brand->isDirty('slug') && $brand->getOriginal('slug') !== null) {
                $brand->slug = $brand->getOriginal('slug');
            }
        });
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brand_logo')->singleFile();
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

    public function getLogoUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('brand_logo');

        return $media ? $media->getUrl($conversion) : null;
    }
}
