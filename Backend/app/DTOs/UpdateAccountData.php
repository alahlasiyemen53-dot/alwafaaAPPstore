<?php

namespace App\DTOs;

final readonly class UpdateAccountData
{
    public function __construct(
        public string $nameAr,
        public ?string $nameEn,
        public ?int $parentId,
        public bool $isPostable,
        public bool $isActive,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            nameAr: trim((string) $data['name_ar']),
            nameEn: isset($data['name_en']) && trim((string) $data['name_en']) !== '' ? trim((string) $data['name_en']) : null,
            parentId: ! empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            isPostable: filter_var($data['is_postable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            isActive: filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
        );
    }
}
