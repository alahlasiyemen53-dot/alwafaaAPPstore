<?php

namespace App\Http\Controllers;

use App\Actions\ActivateProductAction;
use App\Actions\CreateProductAction;
use App\Actions\DeactivateProductAction;
use App\Actions\UpdateProductAction;
use App\Actions\UploadProductMediaAction;
use App\Actions\UploadVariantMediaAction;
use App\Data\CreateProductData;
use App\Data\ProductListData;
use App\Data\UpdateProductData;
use App\Http\Requests\Admin\IndexProductRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Queries\AdminAttributeQuery;
use App\Queries\AdminProductQuery;
use App\Queries\AdminProductVariantQuery;
use App\Queries\GetProductRatingSummaryQuery;
use App\Services\CatalogMediaPresenter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminProductWebController extends Controller
{
    public function index(IndexProductRequest $request, AdminProductQuery $query): View
    {
        return view('admin.products.index', [
            'products' => $query->paginate(ProductListData::fromValidated($request->validated())),
            'brands' => $query->activeBrands(),
            'categories' => $query->activeCategoryOptions(),
        ]);
    }

    public function create(AdminProductQuery $query, AdminAttributeQuery $attributeQuery): View
    {
        return $this->form($query, $attributeQuery);
    }

    public function store(
        StoreProductRequest $request,
        CreateProductAction $action,
        UploadProductMediaAction $productMedia,
        UploadVariantMediaAction $variantMedia,
    ): RedirectResponse {
        try {
            $product = $action->execute(CreateProductData::fromValidated($request->validated()));
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $imageWarning = $this->attachCreateImages($request, $product, $productMedia, $variantMedia);

        $redirect = redirect()->route('admin.products.show', $product)->with('success', 'تم إنشاء المنتج بنجاح.');
        if ($imageWarning !== null) {
            $redirect->with('warning', $imageWarning);
        }

        return $redirect;
    }

    public function show(
        Product $product,
        AdminProductVariantQuery $variantQuery,
        AdminAttributeQuery $attributeQuery,
        CatalogMediaPresenter $mediaPresenter,
        GetProductRatingSummaryQuery $ratingSummaryQuery,
    ): View {
        $product->loadMissing(['brand', 'category', 'supplier', 'media']);

        return view('admin.products.show', [
            'product' => $product,
            'productMedia' => $mediaPresenter->presentProduct($product),
            'mediaPresenter' => $mediaPresenter,
            'variants' => $variantQuery->forProduct($product),
            'attributes' => $attributeQuery->activeAttributesWithValues(),
            'currencies' => $variantQuery->activeCurrencies(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'ratingSummary' => $ratingSummaryQuery->execute($product),
        ]);
    }

    public function edit(Product $product, AdminProductQuery $query, AdminAttributeQuery $attributeQuery): View
    {
        return $this->form($query, $attributeQuery, $product);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProductAction $action): RedirectResponse
    {
        try {
            $updated = $action->execute($product, UpdateProductData::fromValidated($request->validated()));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        $redirect = redirect()->route('admin.products.show', $updated)->with('success', 'تم تحديث المنتج بنجاح.');
        if ($updated->getAttribute('supplier_item_numbers_cleared')) {
            $redirect->with('warning', 'تم تغيير المورد ومسح أرقام الصنف لدى المورد على جميع المتغيرات. يرجى إعادة إدخال الأرقام الصحيحة للمورد الجديد.');
        }

        return $redirect;
    }

    public function activate(Product $product, ActivateProductAction $action): RedirectResponse
    {
        try {
            $action->execute($product);
        } catch (ConflictHttpException) {
            return back()->with('error', 'المنتج نشط بالفعل.');
        }

        return back()->with('success', 'تم تفعيل المنتج.');
    }

    public function deactivate(Product $product, DeactivateProductAction $action): RedirectResponse
    {
        try {
            $action->execute($product);
        } catch (ConflictHttpException) {
            return back()->with('error', 'المنتج معطل بالفعل.');
        }

        return back()->with('success', 'تم تعطيل المنتج.');
    }

    private function form(AdminProductQuery $query, AdminAttributeQuery $attributeQuery, ?Product $product = null): View
    {
        return view('admin.products.form', [
            'product' => $product,
            'brands' => $query->activeBrands(),
            'categories' => $query->activeCategoryOptions(),
            'attributes' => $attributeQuery->activeAttributesWithValues(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    private function attachCreateImages(
        StoreProductRequest $request,
        Product $product,
        UploadProductMediaAction $productMedia,
        UploadVariantMediaAction $variantMedia,
    ): ?string {
        $failed = false;

        try {
            $primary = $request->file('primary_image');
            if ($primary instanceof UploadedFile) {
                $productMedia->execute($product, $primary, true);
            }
        } catch (\Throwable) {
            $failed = true;
        }

        $variantFiles = $request->file('variants');
        if (is_array($variantFiles)) {
            $created = $product->variants()->orderBy('id')->get()->values();
            foreach ($created as $index => $variant) {
                $file = $variantFiles[$index]['image'] ?? null;
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                try {
                    $variantMedia->execute($variant, $file, true);
                } catch (\Throwable) {
                    $failed = true;
                }
            }
        }

        return $failed
            ? 'تم إنشاء المنتج، وتعذر رفع إحدى الصور. يمكنك إعادة رفعها من صفحة المنتج.'
            : null;
    }
}
