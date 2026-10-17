<?php

namespace App\Http\Controllers;

use App\Actions\ActivateProductVariantAction;
use App\Actions\CreateProductVariantAction;
use App\Actions\DeactivateProductVariantAction;
use App\Actions\SetDefaultProductVariantAction;
use App\Actions\UpdateProductVariantAction;
use App\Actions\UpdateVariantPricesAction;
use App\Data\ProductVariantData;
use App\Data\UpdateVariantPriceData;
use App\Http\Requests\Admin\StoreProductVariantRequest;
use App\Http\Requests\Admin\UpdateProductVariantRequest;
use App\Http\Requests\Admin\UpdateVariantPricesRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AdminProductVariantWebController extends Controller
{
    public function store(
        StoreProductVariantRequest $request,
        Product $product,
        CreateProductVariantAction $action,
    ): RedirectResponse {
        try {
            $action->execute(ProductVariantData::fromValidated($product->id, $request->validated()));
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.products.show', $product)->with('success', 'تمت إضافة المتغير بنجاح.');
    }

    public function update(
        UpdateProductVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateProductVariantAction $action,
    ): RedirectResponse {
        $this->assertProductVariant($product, $variant);

        try {
            $action->execute($variant, ProductVariantData::fromValidated($product->id, $request->validated()));
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.products.show', $product)->with('success', 'تم تحديث المتغير بنجاح.');
    }

    public function setDefault(
        Product $product,
        ProductVariant $variant,
        SetDefaultProductVariantAction $action,
    ): RedirectResponse {
        $this->assertProductVariant($product, $variant);

        try {
            $action->execute($variant);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تعيين المتغير كافتراضي بنجاح.');
    }

    public function activate(
        Product $product,
        ProductVariant $variant,
        ActivateProductVariantAction $action,
    ): RedirectResponse {
        $this->assertProductVariant($product, $variant);

        try {
            $action->execute($variant);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تفعيل المتغير.');
    }

    public function deactivate(
        Product $product,
        ProductVariant $variant,
        DeactivateProductVariantAction $action,
    ): RedirectResponse {
        $this->assertProductVariant($product, $variant);

        try {
            $action->execute($variant);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تعطيل المتغير.');
    }

    public function updatePrice(
        UpdateVariantPricesRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateVariantPricesAction $action,
    ): RedirectResponse {
        $this->assertProductVariant($product, $variant);

        $action->execute($variant, UpdateVariantPriceData::fromValidated($request->validated()));

        return back()->with('success', 'تم تحديث سعر المتغير بنجاح.');
    }

    private function assertProductVariant(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            abort(404, 'المتغير غير تابع لهذا المنتج.');
        }
    }
}
