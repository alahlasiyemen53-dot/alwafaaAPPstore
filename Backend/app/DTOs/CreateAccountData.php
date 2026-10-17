<?php

namespace App\DTOs;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;

final readonly class CreateAccountData
{
    public function __construct(
        public string $code,
        public string $nameAr,
        public ?string $nameEn,
        public ?int $parentId,
        public AccountType $type,
        public AccountNormalBalance $normalBalance,
        public bool $isPostable = true,
        public bool $isActive = true,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] instanceof AccountType
            ? $data['type']
            : AccountType::from((string) $data['type']);

        $normalBalance = isset($data['normal_balance']) && $data['normal_balance'] instanceof AccountNormalBalance
            ? $data['normal_balance']
            : (isset($data['normal_balance']) && $data['normal_balance'] !== ''
                ? AccountNormalBalance::from((string) $data['normal_balance'])
                : $type->defaultNormalBalance());

        return new self(
            code: trim((string) $data['code']),
            nameAr: trim((string) $data['name_ar']),
            nameEn: isset($data['name_en']) && trim((string) $data['name_en']) !== '' ? trim((string) $data['name_en']) : null,
            parentId: ! empty($data['parent_id']) ? (int) $data['parent_id'] : null,
            type: $type,
            normalBalance: $normalBalance,
            isPostable: filter_var($data['is_postable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            isActive: filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
        );
    }
}
