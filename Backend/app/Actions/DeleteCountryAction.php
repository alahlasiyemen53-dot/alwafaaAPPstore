<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Country;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\OrderAddress;
use App\Services\AuditLogger;
use App\Support\CountryCallingCode;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DeleteCountryAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(Country $country): void
    {
        DB::transaction(function () use ($country): void {
            $reasons = [];

            if ($country->governorates()->exists()) {
                $reasons[] = 'محافظات مرتبطة';
            }
            if (Customer::query()->where('country_id', $country->id)->exists()) {
                $reasons[] = 'عملاء مرتبطون';
            }
            if (CustomerAddress::query()->where('country_id', $country->id)->exists()) {
                $reasons[] = 'عناوين عملاء مرتبطة';
            }
            if (OrderAddress::query()->where('country_id', $country->id)->exists()) {
                $reasons[] = 'عناوين طلبات تاريخية مرتبطة';
            }

            if ($reasons !== []) {
                throw new ConflictHttpException(
                    'لا يمكن حذف الدولة لأنها مستخدمة ('.implode('، ', $reasons).'). عطّل الدولة بدل الحذف.'
                );
            }

            $countryId = (int) $country->id;
            $oldValues = $country->only(['code', 'name_ar', 'name_en', 'calling_code', 'is_active', 'sort_order']);

            $this->auditLogger->record(
                'admin.countries.deleted',
                $country,
                metadata: ['deleted_country_id' => $countryId],
                oldValues: $oldValues,
            );

            $country->delete();
            CountryCallingCode::forgetCache($countryId);
        });
    }
}
