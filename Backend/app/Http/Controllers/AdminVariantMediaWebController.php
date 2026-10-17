<?php

namespace App\Http\Controllers;

use App\Actions\DeleteVariantMediaAction;
use App\Actions\ReorderVariantMediaAction;
use App\Actions\SetPrimaryVariantMediaAction;
use App\Actions\UploadVariantMediaAction;
use App\Data\MediaItemData;
use App\Http\Requests\Admin\ReorderMediaRequest;
use App\Http\Requests\Admin\UploadVariantMediaRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class AdminVariantMediaWebController extends Controller
{
    public function upload(
        UploadVariantMediaRequest $request,
        Product $product,
        ProductVariant $variant,
        UploadVariantMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $this->assertProductVariant($product, $variant);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $isPrimary = $request->boolean('is_primary');
        /** @var string|null $name */
        $name = $request->input('name');

        $media = $action->execute($variant, $file, $isPrimary, $name);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم رفع صورة المتغير بنجاح.',
                'media' => MediaItemData::fromMedia($media),
            ]);
        }

        return back()->with('success', 'تم رفع صورة المتغير بنجاح.');
    }

    public function destroy(
        Request $request,
        Product $product,
        ProductVariant $variant,
        int $media,
        DeleteVariantMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $this->assertProductVariant($product, $variant);

        $action->execute($variant, $media);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف صورة المتغير بنجاح.',
            ]);
        }

        return back()->with('success', 'تم حذف صورة المتغير بنجاح.');
    }

    public function setPrimary(
        Request $request,
        Product $product,
        ProductVariant $variant,
        int $media,
        SetPrimaryVariantMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $this->assertProductVariant($product, $variant);

        $action->execute($variant, $media);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تعيين صورة المتغير كصورة رئيسية.',
            ]);
        }

        return back()->with('success', 'تم تعيين صورة المتغير كصورة رئيسية.');
    }

    public function reorder(
        ReorderMediaRequest $request,
        Product $product,
        ProductVariant $variant,
        ReorderVariantMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $this->assertProductVariant($product, $variant);

        /** @var array<int> $mediaIds */
        $mediaIds = $request->validated('media_ids');
        $action->execute($variant, $mediaIds);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ ترتيب صور المتغير بنجاح.',
            ]);
        }

        return back()->with('success', 'تم حفظ ترتيب صور المتغير بنجاح.');
    }

    private function assertProductVariant(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            abort(404, 'المتغير غير تابع لهذا المنتج.');
        }
    }
}
