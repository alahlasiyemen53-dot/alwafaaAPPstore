<?php

namespace App\Actions;

use App\DTOs\CreateAccountData;
use App\Models\Account;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateAccountData $data): Account
    {
        return DB::transaction(function () use ($data): Account {
            if (Account::query()->where('code', $data->code)->exists()) {
                throw new ConflictHttpException("رمز الحساب '{$data->code}' مستخدم بالفعل.");
            }

            if ($data->parentId !== null) {
                $parent = Account::find($data->parentId);
                if ($parent === null) {
                    throw new UnprocessableEntityHttpException('الحساب الأب المحدد غير موجود.');
                }
            }

            $userId = auth()->id() > 0 ? auth()->id() : null;

            $account = Account::create([
                'code' => $data->code,
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'parent_id' => $data->parentId,
                'type' => $data->type,
                'normal_balance' => $data->normalBalance,
                'is_postable' => $data->isPostable,
                'is_active' => $data->isActive,
                'description' => $data->description,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.account.created', $account, metadata: [
                'code' => $account->code,
                'type' => $account->type->value,
                'normal_balance' => $account->normal_balance->value,
            ]);

            return $account;
        });
    }
}
