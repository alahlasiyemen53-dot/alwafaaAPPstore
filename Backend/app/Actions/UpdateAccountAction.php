<?php

namespace App\Actions;

use App\DTOs\UpdateAccountData;
use App\Models\Account;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UpdateAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Account $account, UpdateAccountData $data): Account
    {
        return DB::transaction(function () use ($account, $data): Account {
            /** @var Account $lockedAccount */
            $lockedAccount = Account::query()->lockForUpdate()->findOrFail($account->id);

            if ($data->parentId !== null) {
                if ($data->parentId === $lockedAccount->id) {
                    throw new UnprocessableEntityHttpException('لا يمكن للحساب أن يكون حساباً رئيسياً لنفسه.');
                }

                $parent = Account::find($data->parentId);
                if ($parent === null) {
                    throw new UnprocessableEntityHttpException('الحساب الأب المحدد غير موجود.');
                }

                // Check if the proposed parent is currently a descendant of this account (cycle prevention)
                $checkDescendant = $parent;
                while ($checkDescendant->parent_id !== null) {
                    if ($checkDescendant->parent_id === $lockedAccount->id) {
                        throw new UnprocessableEntityHttpException('لا يمكن تعيين حساب فرعي كحساب رئيسي (منع الحلقات التكرارية).');
                    }
                    $checkDescendant = $checkDescendant->parent;
                    if ($checkDescendant === null) {
                        break;
                    }
                }
            }

            // If account has journal lines and user tries to make it non-postable
            if (! $data->isPostable && $lockedAccount->is_postable) {
                $hasLines = $lockedAccount->journalLines()->exists();
                if ($hasLines) {
                    throw new UnprocessableEntityHttpException('لا يمكن تحويل الحساب إلى تجميعي لوجود قيود محاسبية مسجلة عليه.');
                }
            }

            $userId = auth()->id() > 0 ? auth()->id() : null;

            $lockedAccount->update([
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'parent_id' => $data->parentId,
                'is_postable' => $data->isPostable,
                'is_active' => $data->isActive,
                'description' => $data->description,
                'updated_by' => $userId,
            ]);

            $this->auditLogger->record('admin.account.updated', $lockedAccount, metadata: [
                'code' => $lockedAccount->code,
                'is_postable' => $lockedAccount->is_postable,
                'is_active' => $lockedAccount->is_active,
            ]);

            return $lockedAccount->fresh(['parent', 'children']);
        });
    }
}
