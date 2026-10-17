<?php

namespace App\Actions;

use App\DTOs\ElectronicWalletAccountData;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\ElectronicWalletAccount;
use App\Services\AuditLogger;
use App\Support\TreasuryMultiCurrencySync;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateElectronicWalletAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ElectronicWalletAccountData $data): ElectronicWalletAccount
    {
        return DB::transaction(function () use ($data): ElectronicWalletAccount {
            if (ElectronicWalletAccount::where('code', $data->code)->exists()) {
                throw new ConflictHttpException("كود المحفظة '{$data->code}' مستخدم مسبقاً.");
            }

            $glAccount = Account::findOrFail($data->glAccountId);
            if (! $glAccount->is_active || ! $glAccount->is_postable) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
            }

            if ($glAccount->type !== AccountType::Asset) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام للمحفظة يجب أن يكون من نوع الأصول (Asset).');
            }

            $currencyIds = $data->currencyIds !== []
                ? $data->currencyIds
                : [$data->currencyId];
            [$primaryCurrencyId, $normalizedIds] = TreasuryMultiCurrencySync::normalizeCurrencyIds($currencyIds);

            $user = auth()->user();
            $userId = $user instanceof \App\Models\User ? (int) $user->id : null;

            $wallet = ElectronicWalletAccount::create([
                'code' => $data->code,
                'provider_name' => $data->providerName,
                'account_label' => $data->accountLabel,
                'account_identifier' => $data->accountIdentifier,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($wallet, $normalizedIds, $userId);

            $this->auditLogger->record('admin.electronic_wallet.created', $wallet, metadata: [
                'code' => $wallet->code,
                'provider_name' => $wallet->provider_name,
                'account_identifier' => $wallet->account_identifier,
                'currency_ids' => $normalizedIds,
            ]);

            return $wallet->fresh(['currencyAccounts']);
        });
    }
}
