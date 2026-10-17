<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Models\District;
use App\Models\Governorate;
use App\Support\CountryCallingCode;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MasterDataService
{
    public function __construct(
        private readonly SlugGenerator $slugGenerator = new SlugGenerator,
    ) {}

    public function prepareStoreData(string $entity, string $modelClass, array $data): array
    {
        if (in_array($entity, ['brands', 'categories', 'governorates', 'cities', 'districts'], true)) {
            if (empty($data['slug'])) {
                $name = ! empty($data['name_en']) ? (string) $data['name_en'] : ((string) ($data['name_ar'] ?? 'item'));
                $data['slug'] = $this->slugGenerator->generate($name, $modelClass);
            }
        }

        if (in_array($entity, ['delivery-methods', 'warehouses', 'currencies', 'units'], true) && empty($data['code'])) {
            $name = ! empty($data['name_en']) ? (string) $data['name_en'] : ((string) ($data['name_ar'] ?? 'item'));
            $maxLength = match ($entity) {
                'warehouses' => 32,
                'currencies' => 3,
                'units' => 32,
                default => 64,
            };
            $data['code'] = strtoupper($this->slugGenerator->generate($name, $modelClass, null, 'code', $maxLength));
        }

        if ($entity === 'countries' && empty($data['code'])) {
            $name = ! empty($data['name_en']) ? (string) $data['name_en'] : ((string) ($data['name_ar'] ?? 'XX'));
            $data['code'] = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name) ?: 'XX', 0, 2));
        }

        if ($entity === 'countries') {
            if (isset($data['code'])) {
                $data['code'] = strtoupper((string) $data['code']);
            }
            $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
            CountryCallingCode::forgetCache();
        }

        if ($entity === 'delivery-methods' && ! array_key_exists('price', $data)) {
            $data['price'] = 0;
        }

        return $data;
    }

    public function prepareUpdateData(string $entity, array $data): array
    {
        if (in_array($entity, ['brands', 'categories', 'governorates', 'cities', 'districts'], true)) {
            unset($data['slug']);
        }

        if (in_array($entity, ['delivery-methods', 'warehouses', 'countries', 'currencies', 'units'], true)) {
            unset($data['code']);
        }

        if ($entity === 'delivery-methods') {
            unset($data['price']);
        }

        if ($entity === 'countries') {
            unset($data['code']);
            CountryCallingCode::forgetCache();
        }

        return $data;
    }

    public function saveCurrency(array $data, ?Currency $currency = null): Currency
    {
        return DB::transaction(function () use ($data, $currency): Currency {
            $currency ??= new Currency;
            $currency->fill($data);

            $isDefaultRequested = (bool) ($data['is_default'] ?? false);
            $baseCurrencyId = (int) Setting::query()->where('key', 'default_currency_id')->value('value');

            if ($currency->exists && $baseCurrencyId === $currency->id) {
                $currency->is_default = true;
            } elseif ($isDefaultRequested) {
                $currency->is_default = true;
            }

            $currency->save();

            if ($currency->is_default) {
                Currency::query()->where('id', '!=', $currency->id)->update(['is_default' => false]);
                Setting::query()->updateOrCreate(
                    ['key' => 'default_currency_id'],
                    ['value' => (string) $currency->id, 'type' => 'integer']
                );
            } elseif (! Setting::query()->where('key', 'default_currency_id')->exists() && $currency->is_active) {
                $existingDefault = Currency::query()->where('is_default', true)->where('id', '!=', $currency->id)->first();
                if ($existingDefault) {
                    Setting::query()->create([
                        'key' => 'default_currency_id',
                        'value' => (string) $existingDefault->id,
                        'type' => 'integer',
                    ]);
                } else {
                    Setting::query()->create([
                        'key' => 'default_currency_id',
                        'value' => (string) $currency->id,
                        'type' => 'integer',
                    ]);
                    $currency->update(['is_default' => true]);
                }
            }

            return $currency->fresh();
        });
    }

    public function setCurrencyState(Currency $currency, bool $active): Currency
    {
        return DB::transaction(function () use ($currency, $active): Currency {
            $baseCurrencyId = (int) Setting::query()->where('key', 'default_currency_id')->value('value');
            $isBase = ($baseCurrencyId > 0 && $currency->id === $baseCurrencyId) || $currency->is_default;

            if (! $active && $isBase) {
                throw new ConflictHttpException('لا يمكن تعطيل العملة الأساسية المعتمدة للنظام.');
            }

            $currency->update(['is_active' => $active]);

            return $currency->fresh();
        });
    }

    public function validateLocation(?int $governorateId, ?int $cityId, ?int $districtId): void
    {
        $city = $cityId ? City::query()->findOrFail($cityId) : null;
        $district = $districtId ? District::query()->findOrFail($districtId) : null;
        if ($city && $governorateId !== $city->governorate_id || $district && $cityId !== $district->city_id) {
            throw new ConflictHttpException('The location hierarchy is invalid.');
        }
    }

    public function validateCategoryParent(Category $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($parentId === $category->id) {
            throw new ConflictHttpException('A category cannot be its own parent.');
        }
        $parent = Category::query()->findOrFail($parentId);
        while ($parent->parent_id !== null) {
            if ($parent->parent_id === $category->id) {
                throw new ConflictHttpException('A category cannot be moved below its descendant.');
            }
            $parent = Category::query()->findOrFail($parent->parent_id);
        }
    }

    public function saveWithLocation(Model $model, array $data): Model
    {
        $this->validateLocation($data['governorate_id'] ?? null, $data['city_id'] ?? null, $data['district_id'] ?? null);
        $model->fill($data);
        $model->save();

        return $model->fresh();
    }

    /**
     * Deactivate an entity and cascade to dependent children so admin can
     * disable a governorate/city/category without a hard conflict page.
     */
    public function deactivateWithDescendants(Model $model): void
    {
        DB::transaction(function () use ($model): void {
            if ($model instanceof Governorate) {
                $cityIds = $model->cities()->pluck('id');
                if ($cityIds->isNotEmpty()) {
                    District::query()->whereIn('city_id', $cityIds)->update(['is_active' => false]);
                    City::query()->whereIn('id', $cityIds)->update(['is_active' => false]);
                }
                $model->update(['is_active' => false]);

                return;
            }

            if ($model instanceof City) {
                $model->districts()->update(['is_active' => false]);
                $model->update(['is_active' => false]);

                return;
            }

            if ($model instanceof Category) {
                $this->deactivateCategoryTree($model);

                return;
            }

            $model->update(['is_active' => false]);
        });
    }

    private function deactivateCategoryTree(Category $category): void
    {
        $category->loadMissing('children');
        foreach ($category->children as $child) {
            $this->deactivateCategoryTree($child);
        }
        $category->update(['is_active' => false]);
    }
}
