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

final class CreateCashboxAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CashboxData $data): Cashbox
    {
        return DB::transaction(function () use ($data): Cashbox {
            if (Cashbox::where('code', $data->code)->exists()) {
                throw new ConflictHttpException("كود الصندوق '{$data->code}' مستخدم مسبقاً.");
            }

            $glAccount = Account::findOrFail($data->glAccountId);
            if (! $glAccount->is_active || ! $glAccount->is_postable) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
            }

            if ($glAccount->type !== AccountType::Asset) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام للصندوق يجب أن يكون من نوع الأصول (Asset).');
            }

            $currencyIds = $data->currencyIds !== []
                ? $data->currencyIds
                : [$data->currencyId];
            [$primaryCurrencyId, $normalizedIds] = TreasuryMultiCurrencySync::normalizeCurrencyIds($currencyIds);

            $user = auth()->user();
            $userId = $user instanceof \App\Models\User ? (int) $user->id : null;

            $cashbox = Cashbox::create([
                'code' => $data->code,
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($cashbox, $normalizedIds, $userId);

            $this->auditLogger->record('admin.cashbox.created', $cashbox, metadata: [
                'code' => $cashbox->code,
                'name_ar' => $cashbox->name_ar,
                'currency_ids' => $normalizedIds,
            ]);

            return $cashbox->fresh(['currencyAccounts']);
        });
    }
}
