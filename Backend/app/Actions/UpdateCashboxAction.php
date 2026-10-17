<?php

namespace App\Actions;

use App\DTOs\CashboxData;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Cashbox;
use App\Services\AuditLogger;
use App\Support\TreasuryMultiCurrencySync;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UpdateCashboxAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Cashbox $cashbox, CashboxData $data): Cashbox
    {
        if (Cashbox::where('code', $data->code)->where('id', '!=', $cashbox->id)->exists()) {
            throw new ConflictHttpException("كود الصندوق '{$data->code}' مستخدم مسبقاً.");
        }

        $currencyIds = $data->currencyIds !== []
            ? $data->currencyIds
            : [$data->currencyId];

        $cashbox->loadMissing('currencyAccounts');
        $existingIds = $cashbox->currencyAccounts
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        [$primaryCurrencyId, $normalizedIds, $primaryChanging] = TreasuryMultiCurrencySync::resolveUpdateCurrencySelection(
            (int) $cashbox->currency_id,
            $currencyIds,
            $existingIds,
        );

        $glChanging = (int) $data->glAccountId !== (int) $cashbox->gl_account_id;

        if ($primaryChanging || $glChanging) {
            if ($cashbox->currencyAccounts->count() > 1) {
                throw new ConflictHttpException('لا يمكن تغيير العملة الأساسية أو حساب الأستاذ العام الرئيسي لهذا الصندوق بعد فتح عملات إضافية له. أضف عملات جديدة دون تغيير الأساسي، أو عدّل من شاشة إدارة عملات حساب الخزينة.');
            }

            if ($cashbox->currencyAccounts->count() === 1 && $cashbox->currencyAccounts->first()->hasPostedHistory()) {
                throw new ConflictHttpException('لا يمكن تغيير العملة أو حساب الأستاذ العام لهذا الصندوق بعد ترحيل حركات مالية عليه.');
            }
        }

        $glAccount = Account::findOrFail($data->glAccountId);
        if (! $glAccount->is_active || ! $glAccount->is_postable) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
        }

        if ($glAccount->type !== AccountType::Asset) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام للصندوق يجب أن يكون من نوع الأصول (Asset).');
        }

        return DB::transaction(function () use ($cashbox, $data, $primaryCurrencyId, $normalizedIds, $primaryChanging, $glChanging): Cashbox {
            $userId = auth()->id() > 0 ? auth()->id() : null;

            $cashbox->update([
                'code' => $data->code,
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'updated_by' => $userId,
            ]);

            if ($primaryChanging || $glChanging) {
                $cashbox->currencyAccounts()->first()?->update([
                    'currency_id' => $primaryCurrencyId,
                    'gl_account_id' => $data->glAccountId,
                    'updated_by' => $userId,
                ]);
            }

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($cashbox->fresh(), $normalizedIds, $userId);

            $this->auditLogger->record('admin.cashbox.updated', $cashbox, metadata: [
                'code' => $cashbox->code,
                'name_ar' => $cashbox->name_ar,
                'currency_ids' => $normalizedIds,
            ]);

            return $cashbox->fresh(['currencyAccounts']);
        });
    }
}
