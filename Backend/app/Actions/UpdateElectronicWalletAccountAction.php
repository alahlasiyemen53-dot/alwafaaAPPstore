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

final class UpdateElectronicWalletAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(ElectronicWalletAccount $wallet, ElectronicWalletAccountData $data): ElectronicWalletAccount
    {
        if (ElectronicWalletAccount::where('code', $data->code)->where('id', '!=', $wallet->id)->exists()) {
            throw new ConflictHttpException("كود المحفظة '{$data->code}' مستخدم مسبقاً.");
        }

        $currencyIds = $data->currencyIds !== []
            ? $data->currencyIds
            : [$data->currencyId];

        $wallet->loadMissing('currencyAccounts');
        $existingIds = $wallet->currencyAccounts
            ->pluck('currency_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        [$primaryCurrencyId, $normalizedIds, $primaryChanging] = TreasuryMultiCurrencySync::resolveUpdateCurrencySelection(
            (int) $wallet->currency_id,
            $currencyIds,
            $existingIds,
        );

        $glChanging = (int) $data->glAccountId !== (int) $wallet->gl_account_id;

        if ($primaryChanging || $glChanging) {
            if ($wallet->currencyAccounts->count() > 1) {
                throw new ConflictHttpException('لا يمكن تغيير العملة الأساسية أو حساب الأستاذ العام الرئيسي لهذه المحفظة بعد فتح عملات إضافية لها. أضف عملات جديدة دون تغيير الأساسي، أو عدّل من شاشة إدارة عملات حساب الخزينة.');
            }

            if ($wallet->currencyAccounts->count() === 1 && $wallet->currencyAccounts->first()->hasPostedHistory()) {
                throw new ConflictHttpException('لا يمكن تغيير العملة أو حساب الأستاذ العام لهذه المحفظة بعد ترحيل حركات مالية عليها.');
            }
        }

        $glAccount = Account::findOrFail($data->glAccountId);
        if (! $glAccount->is_active || ! $glAccount->is_postable) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام المحدد غير نشط أو غير قابل للترحيل.');
        }

        if ($glAccount->type !== AccountType::Asset) {
            throw new UnprocessableEntityHttpException('حساب الأستاذ العام للمحفظة يجب أن يكون من نوع الأصول (Asset).');
        }

        return DB::transaction(function () use ($wallet, $data, $primaryCurrencyId, $normalizedIds, $primaryChanging, $glChanging): ElectronicWalletAccount {
            $userId = auth()->id() > 0 ? auth()->id() : null;

            $wallet->update([
                'code' => $data->code,
                'provider_name' => $data->providerName,
                'account_label' => $data->accountLabel,
                'account_identifier' => $data->accountIdentifier,
                'currency_id' => $primaryCurrencyId,
                'gl_account_id' => $data->glAccountId,
                'is_active' => $data->isActive,
                'notes' => $data->notes,
                'updated_by' => $userId,
            ]);

            if ($primaryChanging || $glChanging) {
                $wallet->currencyAccounts()->first()?->update([
                    'currency_id' => $primaryCurrencyId,
                    'gl_account_id' => $data->glAccountId,
                    'updated_by' => $userId,
                ]);
            }

            TreasuryMultiCurrencySync::ensureCurrencyAccounts($wallet->fresh(), $normalizedIds, $userId);

            $this->auditLogger->record('admin.electronic_wallet.updated', $wallet, metadata: [
                'code' => $wallet->code,
                'provider_name' => $wallet->provider_name,
                'currency_ids' => $normalizedIds,
            ]);

            return $wallet->fresh(['currencyAccounts']);
        });
    }
}
