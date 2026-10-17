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

final class CreateBankAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(BankAccountData $data): BankAccount
    {
        return DB::transaction(function () use ($data): BankAccount {
            if (BankAccount::where('code', $data->code)->exists()) {
                throw new ConflictHttpException("كود الحساب البنكي '{$data->code}' مستخدم مسبقاً.");
            }

            $glAccount = Account::findOrFail($data->glAccountId);
            if (! $glAccount->is_active || ! $glAccount->is_postable) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
            }

            if ($glAccount->type !== AccountType::Asset) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام للحساب البنكي يجب أن يكون من نوع الأصول (Asset).');
            }

            $currencyIds = $data->currencyIds !== []
                ? $data->currencyIds
                : [$data->currencyId];
            [$primaryCurrencyId, $normalizedIds] = TreasuryMultiCurrencySync::normalizeCurrencyIds($currencyIds);

            $user = auth()->user();
            $userId = $user instanceof \App\Models\User ? (int) $user->id : null;

            $bankAccount = BankAccount::create([
                'code' => $data->code,
                'bank_name' => $data->bankName,
                'account_name' => $data->accountName,
                'account_number' => $data->accountNumber,
                'iban' => $data->iban,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($bankAccount, $normalizedIds, $userId);

            $this->auditLogger->record('admin.bank_account.created', $bankAccount, metadata: [
                'code' => $bankAccount->code,
                'bank_name' => $bankAccount->bank_name,
                'account_number' => $bankAccount->account_number,
                'currency_ids' => $normalizedIds,
            ]);

            return $bankAccount->fresh(['currencyAccounts']);
        });
    }
}
