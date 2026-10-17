<?php

namespace App\Actions;

use App\DTOs\BankAccountData;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankAccount;
use App\Services\AuditLogger;
use App\Support\TreasuryMultiCurrencySync;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class UpdateBankAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BankAccount $bankAccount, BankAccountData $data): BankAccount
    {
        if (BankAccount::where('code', $data->code)->where('id', '!=', $bankAccount->id)->exists()) {
            throw new ConflictHttpException("كود الحساب البنكي '{$data->code}' مستخدم مسبقاً.");
        }

        $currencyIds = $data->currencyIds !== []
            ? $data->currencyIds
            : [$data->currencyId];

        $bankAccount->loadMissing('currencyAccounts');
        $existingIds = $bankAccount->currencyAccounts
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        [$primaryCurrencyId, $normalizedIds, $primaryChanging] = TreasuryMultiCurrencySync::resolveUpdateCurrencySelection(
            (int) $bankAccount->currency_id,
            $currencyIds,
            $existingIds,
        );

        $glChanging = (int) $data->glAccountId !== (int) $bankAccount->gl_account_id;

        if ($primaryChanging || $glChanging) {
            if ($bankAccount->currencyAccounts->count() > 1) {
                throw new ConflictHttpException('لا يمكن تغيير العملة الأساسية أو حساب الأستاذ العام الرئيسي لهذا الحساب البنكي بعد فتح عملات إضافية له. أضف عملات جديدة دون تغيير الأساسي، أو عدّل من شاشة إدارة عملات حساب الخزينة.');
            }

            if ($bankAccount->currencyAccounts->count() === 1 && $bankAccount->currencyAccounts->first()->hasPostedHistory()) {
                throw new ConflictHttpException('لا يمكن تغيير العملة أو حساب الأستاذ العام لهذا الحساب البنكي بعد ترحيل حركات مالية عليه.');
            }
        }

        $glAccount = Account::findOrFail($data->glAccountId);
        if (! $glAccount->is_active || ! $glAccount->is_postable) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
        }

        if ($glAccount->type !== AccountType::Asset) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام للحساب البنكي يجب أن يكون من نوع الأصول (Asset).');
        }

        return DB::transaction(function () use ($bankAccount, $data, $primaryCurrencyId, $normalizedIds, $primaryChanging, $glChanging): BankAccount {
            $userId = auth()->id() > 0 ? auth()->id() : null;

            $bankAccount->update([
                'code' => $data->code,
                'bank_name' => $data->bankName,
                'account_name' => $data->accountName,
                'account_number' => $data->accountNumber,
                'iban' => $data->iban,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'updated_by' => $userId,
            ]);

            if ($primaryChanging || $glChanging) {
                $bankAccount->currencyAccounts()->first()?->update([
                    'currency_id' => $primaryCurrencyId,
                    'gl_account_id' => $data->glAccountId,
                    'updated_by' => $userId,
                ]);
            }

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($bankAccount->fresh(), $normalizedIds, $userId);

            $this->auditLogger->record('admin.bank_account.updated', $bankAccount, metadata: [
                'code' => $bankAccount->code,
                'bank_name' => $bankAccount->bank_name,
                'currency_ids' => $normalizedIds,
            ]);

            return $bankAccount->fresh(['currencyAccounts']);
        });
    }
}
