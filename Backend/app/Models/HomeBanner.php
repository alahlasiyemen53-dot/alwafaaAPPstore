<?php

namespace App\Models;

use App\Enums\BannerActionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $title_ar
 * @property string|null $title_en
 * @property string|null $subtitle_ar
 * @property string|null $subtitle_en
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property BannerActionType $action_type
 * @property string|null $action_reference
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class HomeBanner extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $table = 'home_banners';

    protected $fillable = [
        'title_ar',
        'title_en',
        'subtitle_ar',
        'subtitle_en',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
        'action_type',
        'action_reference',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'action_type' => BannerActionType::class,
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'action_type' => BannerActionType::None->value,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_images')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->format('webp')
            ->quality(80)
            ->width(300)
            ->height(150);

        $this->addMediaConversion('medium')
            ->nonQueued()
            ->format('webp')
            ->quality(82)
            ->width(800)
            ->height(400);

        $this->addMediaConversion('large')
            ->nonQueued()
            ->format('webp')
            ->quality(85)
            ->width(1400)
            ->height(700);
    }

    public function getBannerImageUrl(string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia('banner_images');

        return $media ? $media->getUrl($conversion) : null;
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopeCurrentlyScheduled(Builder $query, ?Carbon $now = null): Builder
    {
        $timestamp = $now ?? Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $timestamp))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $timestamp));
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
    }

    public function getDestinationEntity(): ?Model
    {
        if (! $this->action_reference || $this->action_type === BannerActionType::None) {
            return null;
        }

        return match ($this->action_type) {
            BannerActionType::Product => Product::query()->find($this->action_reference),
            BannerActionType::Category => Category::query()->find($this->action_reference),
            BannerActionType::Promotion => Promotion::query()->find($this->action_reference),
            default => null,
        };
    }

    public function getDestinationLabel(): string
    {
        $entity = $this->getDestinationEntity();

        return match ($this->action_type) {
            BannerActionType::None => 'بدون وجهة',
            BannerActionType::Product => $entity ? 'منتج: ' . $entity->name_ar : 'منتج غير موجود (#'.$this->action_reference.')',
            BannerActionType::Category => $entity ? 'تصنيف: ' . $entity->name_ar : 'تصنيف غير موجود (#'.$this->action_reference.')',
            BannerActionType::Promotion => $entity ? 'عرض: ' . $entity->name : 'عرض غير موجود (#'.$this->action_reference.')',
        };
    }
}
