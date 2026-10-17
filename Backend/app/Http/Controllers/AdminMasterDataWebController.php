<?php

namespace App\Http\Controllers;

use App\Actions\DeleteBrandLogoAction;
use App\Actions\DeleteCategoryImageAction;
use App\Actions\UploadBrandLogoAction;
use App\Actions\UploadCategoryImageAction;
use App\Http\Requests\Admin\ExchangeRateRequest;
use App\Http\Requests\Admin\MasterDataWebRequest;
use App\Http\Requests\Admin\SettingsRequest;
use App\Http\Requests\Admin\UploadBrandLogoRequest;
use App\Http\Requests\Admin\UploadCategoryImageRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DeliveryMethod;
use App\Models\District;
use App\Models\ExchangeRate;
use App\Models\Governorate;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Models\TreasuryTransaction;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use App\Services\CurrencyConverter;
use App\Services\AppReleaseService;
use App\Services\CustomerSupportService;
use App\Services\GiftWrappingService;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminMasterDataWebController extends Controller
{
    private const MODELS = [
        'countries' => Country::class,
        'currencies' => Currency::class, 'units' => Unit::class, 'governorates' => Governorate::class,
        'cities' => City::class, 'districts' => District::class, 'brands' => Brand::class,
        'categories' => Category::class, 'warehouses' => Warehouse::class,
        'delivery-methods' => DeliveryMethod::class,
    ];

    public function index(Request $request): View
    {
        $entity = $request->segment(3);
        $query = $this->model($entity)::query();
        $term = trim((string) $request->query('search', ''));
        if ($term !== '') {
            if ($entity === 'countries') {
                $fields = ['name_ar', 'name_en', 'code', 'calling_code'];
            } else {
                $fields = in_array($entity, ['currencies', 'units', 'warehouses', 'delivery-methods'], true) ? ['name_ar', 'name_en', 'code'] : ['name_ar', 'name_en', 'slug'];
            }
            $query->where(fn ($q) => collect($fields)->each(fn ($field, $index) => $index === 0 ? $q->where($field, 'like', "%$term%") : $q->orWhere($field, 'like', "%$term%")));
        }
        if ($entity === 'cities') {
            $query->with('governorate');
        }
        if ($entity === 'governorates') {
            $query->with('country')->orderBy('country_id')->orderBy('name_ar');
        }
        if ($entity === 'districts') {
            $query->with('city');
        }
        if ($entity === 'categories') {
            $query->with(['parent', 'media'])->withCount(['children', 'products'])->orderBy('sort_order');
        }
        if ($entity === 'brands') {
            $query->with('media')->withCount('products')->orderBy('sort_order');
        }
        if ($entity === 'warehouses') {
            $query->with(['governorate', 'city', 'district']);
        }

        if ($entity === 'delivery-methods') {
            $query->orderBy('sort_order')->orderBy('id');
        }
        if ($entity === 'countries') {
            $query->orderBy('sort_order')->orderBy('name_ar')->orderBy('id');
        }

        $query->when($request->filled('status'), fn ($builder) => $builder->where('is_active', $request->string('status')->toString() === 'active'));

        return view('admin.master-data.index', ['entity' => $entity, 'entityLabel' => $this->label($entity), 'records' => $query->paginate(20)->withQueryString(), 'columns' => $this->columns($entity)]);
    }

    public function create(Request $request, CurrencyConverter $currencyConverter): View
    {
        $entity = $request->segment(3);

        return view('admin.master-data.form', [
            'entity' => $entity,
            'entityLabel' => $this->label($entity),
            'record' => null,
            'options' => $this->options($entity),
            'baseCurrency' => $currencyConverter->getBaseCurrency(),
        ]);
    }

    public function store(MasterDataWebRequest $request, MasterDataService $service, AuditLogger $audit): RedirectResponse
    {
        $entity = $request->segment(3);
        $model = $this->model($entity);
        $data = $service->prepareStoreData($entity, $model, $request->validated());
        $record = in_array($entity, ['cities', 'districts', 'warehouses'], true)
            ? $service->saveWithLocation(new $model, $data)
            : ($entity === 'currencies' ? $service->saveCurrency($data) : $model::query()->create($data));
        $audit->record("admin.$entity.created", $record, $request);

        return redirect()->route("admin.master-data.$entity.index")->with('success', 'تم إنشاء السجل بنجاح.');
    }

    public function edit(Request $request, int $record, CurrencyConverter $currencyConverter): View
    {
        $entity = $request->segment(3);

        return view('admin.master-data.form', [
            'entity' => $entity,
            'entityLabel' => $this->label($entity),
            'record' => $this->model($entity)::query()->findOrFail($record),
            'options' => $this->options($entity),
            'baseCurrency' => $currencyConverter->getBaseCurrency(),
        ]);
    }

    public function update(MasterDataWebRequest $request, int $record, MasterDataService $service, AuditLogger $audit): RedirectResponse
    {
        $entity = $request->segment(3);
        $model = $this->model($entity)::query()->findOrFail($record);
        $data = $service->prepareUpdateData($entity, $request->validated());
        if ($entity === 'categories') {
            $service->validateCategoryParent($model, $data['parent_id'] ?? null);
        }
        if ($entity === 'currencies') {
            $service->saveCurrency($data, $model);
        } elseif (in_array($entity, ['cities', 'districts', 'warehouses'], true)) {
            $service->saveWithLocation($model, $data);
        } else {
            $model->update($data);
        }
        $audit->record("admin.$entity.updated", $model, $request);

        return redirect()->route("admin.master-data.$entity.index")->with('success', 'تم تحديث السجل بنجاح.');
    }

    public function state(Request $request, string $entity, int $record, bool $active, MasterDataService $service, AuditLogger $audit): RedirectResponse
    {
        $model = $this->model($entity)::query()->findOrFail($record);

        try {
            if ($active) {
                if ($entity === 'currencies') {
                    $service->setCurrencyState($model, true);
                } else {
                    $model->update(['is_active' => true]);
                }
            } elseif ($entity === 'currencies') {
                $service->setCurrencyState($model, false);
            } else {
                $service->deactivateWithDescendants($model);
            }
        } catch (ConflictHttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($entity === 'countries') {
            \App\Support\CountryCallingCode::forgetCache((int) $model->id);
        }

        $audit->record("admin.$entity.".($active ? 'activated' : 'deactivated'), $model, $request);

        return back()->with('success', 'تم تحديث الحالة.');
    }

    public function activate(Request $request, int $record, MasterDataService $service, AuditLogger $audit): RedirectResponse
    {
        return $this->state($request, $request->segment(3), $record, true, $service, $audit);
    }

    public function deactivate(Request $request, int $record, MasterDataService $service, AuditLogger $audit): RedirectResponse
    {
        return $this->state($request, $request->segment(3), $record, false, $service, $audit);
    }

    public function deliveryState(Request $request, string $entity, int $record, bool $active, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($entity, ['countries', 'governorates'], true), 404);

        $model = $this->model($entity)::query()->findOrFail($record);
        $model->update(['is_delivery_active' => $active]);

        $audit->record("admin.$entity.delivery_".($active ? 'activated' : 'deactivated'), $model, $request);

        return back()->with('success', 'تم تحديث حالة التوصيل بنجاح.');
    }

    public function deliveryActivate(Request $request, int $record, AuditLogger $audit): RedirectResponse
    {
        return $this->deliveryState($request, $request->segment(3), $record, true, $audit);
    }

    public function deliveryDeactivate(Request $request, int $record, AuditLogger $audit): RedirectResponse
    {
        return $this->deliveryState($request, $request->segment(3), $record, false, $audit);
    }

    public function destroy(Request $request, int $record, \App\Actions\DeleteCountryAction $deleteCountry, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->segment(3) === 'countries', 404);

        $country = Country::query()->findOrFail($record);

        try {
            $deleteCountry->execute($country);
        } catch (ConflictHttpException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.master-data.countries.index')
            ->with('success', 'تم حذف الدولة بأمان.');
    }

    public function uploadBrandLogo(UploadBrandLogoRequest $request, Brand $brand, UploadBrandLogoAction $action): RedirectResponse|JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $media = $action->execute($brand, $file);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم رفع الشعار بنجاح.', 'url' => $media->getUrl(), 'thumb_url' => $media->getUrl('thumb')]);
        }

        return back()->with('success', 'تم رفع الشعار بنجاح.');
    }

    public function deleteBrandLogo(Request $request, Brand $brand, DeleteBrandLogoAction $action): RedirectResponse|JsonResponse
    {
        $action->execute($brand);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف الشعار بنجاح.']);
        }

        return back()->with('success', 'تم حذف الشعار بنجاح.');
    }

    public function uploadCategoryImage(UploadCategoryImageRequest $request, Category $category, UploadCategoryImageAction $action): RedirectResponse|JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $media = $action->execute($category, $file);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم رفع الصورة بنجاح.', 'url' => $media->getUrl(), 'thumb_url' => $media->getUrl('thumb')]);
        }

        return back()->with('success', 'تم رفع الصورة بنجاح.');
    }

    public function deleteCategoryImage(Request $request, Category $category, DeleteCategoryImageAction $action): RedirectResponse|JsonResponse
    {
        $action->execute($category);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف الصورة بنجاح.']);
        }

        return back()->with('success', 'تم حذف الصورة بنجاح.');
    }

    public function exchangeRates(Request $request, CurrencyConverter $currencyConverter): View
    {
        $baseCurrency = $currencyConverter->getBaseCurrency();

        return view('admin.master-data.exchange-rates', [
            'rates' => ExchangeRate::query()->with(['fromCurrency', 'toCurrency'])->latest('effective_at')->paginate(20)->withQueryString(),
            'currencies' => Currency::query()->where('is_active', true)->where('id', '!=', $baseCurrency->id)->orderBy('code')->get(['id', 'code', 'name_ar']),
            'baseCurrency' => $baseCurrency,
        ]);
    }

    public function storeExchangeRate(ExchangeRateRequest $request, AuditLogger $audit, CurrencyConverter $currencyConverter): RedirectResponse
    {
        $baseCurrency = $currencyConverter->getBaseCurrency();
        $targetCurrencyId = $request->input('target_currency_id', $request->input('from_currency_id'));

        $rate = ExchangeRate::query()->create([
            'from_currency_id' => $targetCurrencyId,
            'to_currency_id' => $baseCurrency->id,
            'rate' => $request->validated('rate'),
            'effective_at' => $request->validated('effective_at'),
        ]);

        $currencyConverter->clearCache();
        $audit->record('admin.exchange_rates.created', $rate, $request);

        return redirect()->route('admin.master-data.exchange-rates')->with('success', 'تم حفظ سعر الصرف مقابل العملة الأساسية بنجاح.');
    }

    public function editExchangeRate(ExchangeRate $exchangeRate, CurrencyConverter $currencyConverter): View
    {
        $baseCurrency = $currencyConverter->getBaseCurrency();

        return view('admin.master-data.exchange-rates', [
            'rates' => ExchangeRate::query()->with(['fromCurrency', 'toCurrency'])->latest('effective_at')->paginate(20),
            'currencies' => Currency::query()->where('is_active', true)->where('id', '!=', $baseCurrency->id)->orderBy('code')->get(['id', 'code', 'name_ar']),
            'editingRate' => $exchangeRate,
            'baseCurrency' => $baseCurrency,
        ]);
    }

    public function updateExchangeRate(ExchangeRateRequest $request, ExchangeRate $exchangeRate, AuditLogger $audit, CurrencyConverter $currencyConverter): RedirectResponse
    {
        $baseCurrency = $currencyConverter->getBaseCurrency();
        $targetCurrencyId = $request->input('target_currency_id', $request->input('from_currency_id', $exchangeRate->from_currency_id));

        $exchangeRate->update([
            'from_currency_id' => $targetCurrencyId,
            'to_currency_id' => $baseCurrency->id,
            'rate' => $request->validated('rate'),
            'effective_at' => $request->validated('effective_at'),
        ]);

        $currencyConverter->clearCache();
        $audit->record('admin.exchange_rates.updated', $exchangeRate, $request);

        return redirect()->route('admin.master-data.exchange-rates')->with('success', 'تم تحديث سعر الصرف.');
    }

    public function settings(CurrencyConverter $currencyConverter, CustomerSupportService $customerSupportService): View
    {
        return view('admin.master-data.settings', [
            'settings' => Setting::query()->whereIn('key', array_keys($this->allowedSettings()))->pluck('value', 'key'),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name_ar']),
            'baseCurrency' => $currencyConverter->getBaseCurrency(),
            'customerSupport' => $customerSupportService->getSettings(),
        ]);
    }

    public function updateSettings(
        SettingsRequest $request,
        AuditLogger $audit,
        CurrencyConverter $currencyConverter,
        GiftWrappingService $giftWrappingService,
        CustomerSupportService $customerSupportService,
        AppReleaseService $appReleaseService
    ): RedirectResponse {
        $values = $request->validated('settings');
        $unknown = array_diff(array_keys($values), array_keys($this->allowedSettings()));
        if ($unknown) {
            throw ValidationException::withMessages(['settings' => ['مفتاح إعداد غير مسموح.']]);
        }

        // Section 17: Base Currency Change Safety
        $currentBaseId = (int) Setting::query()->where('key', 'default_currency_id')->value('value');
        $newBaseId = isset($values['default_currency_id']) ? (int) $values['default_currency_id'] : null;

        if ($currentBaseId > 0 && $newBaseId !== null && $newBaseId !== $currentBaseId) {
            $hasTransactions = Order::query()->exists()
                || JournalEntry::query()->exists()
                || TreasuryTransaction::query()->exists()
                || PurchaseInvoice::query()->exists();

            if ($hasTransactions) {
                throw ValidationException::withMessages([
                    'settings.default_currency_id' => ['لا يمكن تغيير العملة الأساسية للنظام لوجود حركات مالية ومحاسبية مسجلة مسبقاً.'],
                ]);
            }
        }

        $oldSettings = Setting::query()->whereIn('key', array_keys($values))->pluck('value', 'key')->toArray();

        DB::transaction(function () use ($values, $newBaseId) {
            collect($values)->each(fn ($value, $key) => Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $this->allowedSettings()[$key]]));

            if ($newBaseId !== null && $newBaseId > 0) {
                Currency::query()->where('id', $newBaseId)->update(['is_default' => true]);
                Currency::query()->where('id', '!=', $newBaseId)->update(['is_default' => false]);
            }
        });

        $currencyConverter->clearCache();
        $giftWrappingService->clearCache();
        $customerSupportService->clearCache();
        $appReleaseService->clearCache();

        $audit->record('admin.settings.updated', null, $request, [
            'updated_keys' => array_keys($values),
            'new_values' => $values,
        ], oldValues: $oldSettings);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    private function model(string $entity): string
    {
        abort_unless(isset(self::MODELS[$entity]), 404);

        return self::MODELS[$entity];
    }

    private function options(string $entity): array
    {
        return match ($entity) {
            'governorates' => ['countries' => Country::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar'])],
            'cities' => ['governorates' => Governorate::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar'])],
            'districts' => ['cities' => City::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar'])],
            'categories' => ['categories' => Category::query()->orderBy('name_ar')->get(['id', 'name_ar'])],
            'warehouses' => ['governorates' => Governorate::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']), 'cities' => City::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar']), 'districts' => District::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar'])],
            default => [],
        };
    }

    private function columns(string $entity): array
    {
        return match ($entity) {
            'countries' => ['code' => 'ISO', 'name_ar' => 'الاسم', 'calling_code' => 'مفتاح الاتصال', 'sort_order' => 'الترتيب', 'is_active' => 'نشطة (تسجيل)', 'is_delivery_active' => 'التوصيل'],
            'currencies' => ['code' => 'الرمز', 'name_ar' => 'الاسم', 'is_default' => 'افتراضية', 'is_active' => 'نشطة'],
            'units' => ['code' => 'الرمز', 'name_ar' => 'الاسم', 'is_active' => 'نشطة'],
            'governorates' => ['country' => 'الدولة', 'name_ar' => 'الاسم', 'slug' => 'Slug', 'is_active' => 'نشطة', 'is_delivery_active' => 'التوصيل'],
            'delivery-methods' => ['code' => 'الرمز', 'name_ar' => 'الاسم', 'estimated_delivery_ar' => 'مدة التوصيل', 'sort_order' => 'الترتيب', 'is_active' => 'نشطة'],
            'brands' => ['thumbnail' => 'الصورة', 'name_ar' => 'الاسم', 'slug' => 'Slug', 'products_count' => 'عدد الأصناف', 'is_active' => 'نشطة'],
            'categories' => ['thumbnail' => 'الصورة', 'name_ar' => 'الاسم', 'slug' => 'Slug', 'products_count' => 'عدد الأصناف', 'is_active' => 'نشطة'],
            'cities', 'districts' => ['name_ar' => 'الاسم', 'slug' => 'Slug', 'is_active' => 'نشطة'],
            'warehouses' => ['code' => 'الرمز', 'name_ar' => 'الاسم', 'is_active' => 'نشطة'],
            default => [],
        };
    }

    private function label(string $entity): string
    {
        return match ($entity) {
            'countries' => 'الدول',
            'currencies' => 'العملات', 'units' => 'الوحدات', 'governorates' => 'المحافظات',
            'cities' => 'المدن', 'districts' => 'المديريات', 'brands' => 'العلامات التجارية',
            'categories' => 'التصنيفات', 'warehouses' => 'المستودعات',
            'delivery-methods' => 'طرق التوصيل', default => $entity,
        };
    }

    private function allowedSettings(): array
    {
        return [
            'application_name' => 'string',
            'default_locale' => 'string',
            'default_currency_id' => 'integer',
            'support_phone' => 'string',
            'support_email' => 'string',
            'gift_wrapping_enabled' => 'boolean',
            'gift_message_enabled' => 'boolean',
            'gift_message_max_length' => 'integer',
            'support_phone_enabled' => 'boolean',
            'support_whatsapp_enabled' => 'boolean',
            'support_whatsapp_number' => 'string',
            'support_whatsapp_default_message' => 'string',
            'support_hours_text_ar' => 'string',
            'customer_delivery_confirm_hours' => 'integer',
            'app_latest_version' => 'string',
            'app_min_version' => 'string',
            'app_force_update' => 'boolean',
            'app_android_store_url' => 'string',
            'app_ios_store_url' => 'string',
        ];
    }
}
