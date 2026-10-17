<?php

namespace App\Data;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class MediaItemData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fileName,
        public string $mimeType,
        public int $size,
        public int $orderColumn,
        public bool $isPrimary,
        public string $originalUrl,
        public string $thumbUrl,
        public string $smallUrl,
        public string $mediumUrl,
        public string $largeUrl,
        public ?string $createdAt = null,
    ) {}

    public static function fromMedia(Media $media): self
    {
        return new self(
            id: (int) $media->id,
            name: (string) $media->name,
            fileName: (string) $media->file_name,
            mimeType: (string) $media->mime_type,
            size: (int) $media->size,
            orderColumn: (int) $media->order_column,
            isPrimary: (bool) $media->getCustomProperty('is_primary', false),
            originalUrl: $media->getUrl(),
            thumbUrl: $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
            smallUrl: $media->hasGeneratedConversion('small') ? $media->getUrl('small') : $media->getUrl(),
            mediumUrl: $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl(),
            largeUrl: $media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl(),
            createdAt: $media->created_at?->toIso8601String(),
        );
    }
}
