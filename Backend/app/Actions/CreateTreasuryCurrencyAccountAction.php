<?php

namespace App\Actions;

use App\DTOs\CreateTreasuryCurrencyAccountData;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\ElectronicWalletAccount;
use App\Models\TreasuryCurrencyAccount;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateTreasuryCurrencyAccountAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(CreateTreasuryCurrencyAccountData $data, int $userId = 0): TreasuryCurrencyAccount
    {
        return DB::transaction(function () use ($data, $userId): TreasuryCurrencyAccount {
            $parent = match ($data->accountableType) {
                'cashbox', Cashbox::class => Cashbox::query()->lockForUpdate()->find($data->accountableId),
                'bank_account', BankAccount::class => BankAccount::query()->lockForUpdate()->find($data->accountableId),
                'electronic_wallet', ElectronicWalletAccount::class => ElectronicWalletAccount::query()->lockForUpdate()->find($data->accountableId),
                default => null,
            };

            if (! $parent) {
                throw new UnprocessableEntityHttpException('كيان الخزينة الأب غير موجود.');
            }

            $resolvedType = get_class($parent);

            $currency = Currency::where('is_active', true)->find($data->currencyId);
            if (! $currency) {
                throw new UnprocessableEntityHttpException('العملة المحددة غير صالحة أو معطلة.');
            }

            $alreadyExists = TreasuryCurrencyAccount::where('accountable_type', $resolvedType)
                ->where('accountable_id', $parent->id)
                ->where('currency_id', $currency->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyExists) {
                throw new ConflictHttpException("العملة ({$currency->code}) مضافة بالفعل لحساب الخزينة المحدد.");
            }

            $glAccountId = (int) $parent->gl_account_id;
            if ($glAccountId <= 0) {
                throw new UnprocessableEntityHttpException('حساب الأستاذ العام للأب غير معرّف.');
            }

            $currencyAccount = TreasuryCurrencyAccount::create([
                'accountable_type' => $resolvedType,
                'accountable_id' => $parent->id,
                'currency_id' => $currency->id,
                'gl_account_id' => $glAccountId,
                'is_active' => true,
                'notes' => $data->notes,
                'created_by' => $userId > 0 ? $userId : null,
                'updated_by' => $userId > 0 ? $userId : null,
            ]);

            $this->auditLogger->record(
                action: 'treasury_currency_account.created',
                subject: $currencyAccount,
                metadata: [
                    'accountable_type' => $resolvedType,
                    'accountable_id' => $parent->id,
                    'currency' => $currency->code,
                    'gl_account_id' => $glAccountId,
                    'shared_parent_gl' => true,
                ],
            );

            return $currencyAccount;
        });
    }
}
