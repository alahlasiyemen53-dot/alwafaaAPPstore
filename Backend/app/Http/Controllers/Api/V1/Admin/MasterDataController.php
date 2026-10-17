<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExchangeRateRequest;
use App\Http\Requests\Admin\MasterDataRequest;
use App\Http\Requests\Admin\SettingsRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Http\Resources\MasterDataResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Models\District;
use App\Models\ExchangeRate;
use App\Models\Governorate;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AppReleaseService;
use App\Services\AuditLogger;
use App\Services\CustomerSupportService;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MasterDataController extends Controller
{
    private const MODELS = [
        'currencies' => Currency::class, 'units' => Unit::class, 'governorates' => Governorate::class,
        'cities' => City::class, 'districts' => District::class, 'brands' => Brand::class,
        'categories' => Category::class, 'warehouses' => Warehouse::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $entity = $request->segment(4);
        $model = $this->model($entity);
        $query = $model::query();
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $searchFields = match ($entity) {
                'currencies', 'units', 'warehouses' => ['name_ar', 'name_en', 'code'],
                default => ['name_ar', 'name_en', 'slug'],
            };
            $query->where(fn ($q) => collect($searchFields)->each(fn ($field, $index) => $index === 0
                ? $q->where($field, 'like', "%$search%")
                : $q->orWhere($field, 'like', "%$search%")));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($entity === 'cities') {
            $query->with('governorate');
        }
        if ($entity === 'districts') {
            $query->with('city.governorate');
        }
        if ($entity === 'warehouses') {
            $query->with(['governorate', 'city', 'district']);
        }
        if ($entity === 'categories') {
            $query->with('parent')->withCount('children')->orderBy('sort_order');
        }

        return ApiResponse::success(MasterDataResource::collection($query->paginate(min((int) $request->query('per_page', 20), 100))->withQueryString()));
    }

    public function store(MasterDataRequest $request, MasterDataService $service, AuditLogger $audit): JsonResponse
    {
        $entity = $request->segment(4);
        $model = $this->model($entity);
        $data = $service->prepareStoreData($entity, $model, $request->validated());
        if ($entity === 'categories') {
            $service->validateCategoryParent(new Category, $data['parent_id'] ?? null);
        }
        $saved = $entity === 'cities' || $entity === 'districts' || $entity === 'warehouses'
            ? $service->saveWithLocation(new $model, $data)
            : ($entity === 'currencies' ? $service->saveCurrency($data) : $model::query()->create($data));
        $audit->record("admin.$entity.created", $saved);

        return ApiResponse::success(MasterDataResource::make($saved), 'Created.', 201);
    }

    public function update(MasterDataRequest $request, int $masterData, MasterDataService $service, AuditLogger $audit): JsonResponse
    {
        $entity = $request->segment(4);
        $model = $this->model($entity)::query()->findOrFail($masterData);
        $data = $service->prepareUpdateData($entity, $request->validated());
        if ($entity === 'categories') {
            $service->validateCategoryParent($model, $data['parent_id'] ?? null);
        }
        if ($entity === 'currencies') {
            $saved = $service->saveCurrency($data, $model);
        } elseif ($entity === 'cities' || $entity === 'districts' || $entity === 'warehouses') {
            $saved = $service->saveWithLocation($model, $data);
        } else {
            $model->update($data);
            $saved = $model->fresh();
        }
        $audit->record("admin.$entity.updated", $saved);

        return ApiResponse::success(MasterDataResource::make($saved));
    }

    public function state(Request $request, string $entity, int $masterData, bool $active, MasterDataService $service, AuditLogger $audit): JsonResponse
    {
        $model = $this->model($entity)::query()->findOrFail($masterData);
        if ($active) {
            $model->update(['is_active' => true]);
        } elseif ($model instanceof Currency) {
            $service->setCurrencyState($model, false);
        } else {
            $service->deactivateWithDescendants($model);
        }
        $audit->record("admin.$entity.".($active ? 'activated' : 'deactivated'), $model);

        return ApiResponse::success(MasterDataResource::make($model->fresh()));
    }

    public function activate(Request $request, int $masterData, MasterDataService $service, AuditLogger $audit): JsonResponse
    {
        $entity = $request->segment(4);

        return $this->state($request, $entity, $masterData, true, $service, $audit);
    }

    public function deactivate(Request $request, int $masterData, MasterDataService $service, AuditLogger $audit): JsonResponse
    {
        $entity = $request->segment(4);

        return $this->state($request, $entity, $masterData, false, $service, $audit);
    }

    public function exchangeRates(Request $request): JsonResponse
    {
        return ApiResponse::success(ExchangeRateResource::collection(ExchangeRate::query()->with(['fromCurrency', 'toCurrency'])->latest('effective_at')->paginate(min((int) $request->query('per_page', 20), 100))));
    }

    public function storeExchangeRate(ExchangeRateRequest $request, AuditLogger $audit): JsonResponse
    {
        $rate = ExchangeRate::query()->create($request->validated())->load(['fromCurrency', 'toCurrency']);
        $audit->record('admin.exchange_rates.created', $rate);

        return ApiResponse::success(new ExchangeRateResource($rate), 'Created.', 201);
    }

    public function updateExchangeRate(ExchangeRateRequest $request, int $exchangeRate, AuditLogger $audit): JsonResponse
    {
        $rate = ExchangeRate::query()->findOrFail($exchangeRate);
        $rate->update($request->validated());
        $rate->load(['fromCurrency', 'toCurrency']);
        $audit->record('admin.exchange_rates.updated', $rate);

        return ApiResponse::success(new ExchangeRateResource($rate));
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success(Setting::query()->whereIn('key', array_keys(self::allowedSettings()))->pluck('value', 'key'));
    }

    public function updateSettings(
        SettingsRequest $request,
        AuditLogger $audit,
        CustomerSupportService $customerSupportService,
        AppReleaseService $appReleaseService
    ): JsonResponse {
        $unknown = array_diff(array_keys($request->validated('settings')), array_keys(self::allowedSettings()));
        if ($unknown) {
            throw ValidationException::withMessages(['settings' => ['Unknown setting key: '.reset($unknown)]]);
        }
        DB::transaction(fn () => collect($request->validated('settings'))->each(fn ($value, $key) => Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => self::allowedSettings()[$key]])));
        $customerSupportService->clearCache();
        $appReleaseService->clearCache();
        $audit->record('admin.settings.updated', null);

        return $this->settings();
    }

    private function model(string $entity): string
    {
        abort_unless(isset(self::MODELS[$entity]), 404);

        return self::MODELS[$entity];
    }

    private static function allowedSettings(): array
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
            'customer_delivery_confirm_hours' => 'integer',
            'support_hours_text_ar' => 'string',
            'app_latest_version' => 'string',
            'app_min_version' => 'string',
            'app_force_update' => 'boolean',
            'app_android_store_url' => 'string',
            'app_ios_store_url' => 'string',
        ];
    }
}
