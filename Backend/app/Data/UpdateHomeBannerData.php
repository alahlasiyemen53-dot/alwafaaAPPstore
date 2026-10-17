<?php

namespace App\Data;

use App\Enums\BannerActionType;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

final readonly class UpdateHomeBannerData
{
    public function __construct(
        public string $titleAr,
        public ?string $titleEn,
        public ?string $subtitleAr,
        public ?string $subtitleEn,
        public bool $isActive,
        public int $sortOrder,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
        public BannerActionType $actionType,
        public ?string $actionReference,
        public ?UploadedFile $image,
    ) {}

    public static function fromValidated(array $attributes, ?UploadedFile $image = null): self
    {
        return new self(
            $attributes['title_ar'],
            $attributes['title_en'] ?? null,
            $attributes['subtitle_ar'] ?? null,
            $attributes['subtitle_en'] ?? null,
            (bool) ($attributes['is_active'] ?? true),
            (int) ($attributes['sort_order'] ?? 0),
            ! empty($attributes['starts_at']) ? Carbon::parse($attributes['starts_at']) : null,
            ! empty($attributes['ends_at']) ? Carbon::parse($attributes['ends_at']) : null,
            BannerActionType::from($attributes['action_type']),
            $attributes['action_reference'] ?? null,
            $image,
        );
    }
}
