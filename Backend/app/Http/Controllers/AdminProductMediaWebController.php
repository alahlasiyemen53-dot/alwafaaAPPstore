<?php

namespace App\Http\Controllers;

use App\Actions\DeleteProductMediaAction;
use App\Actions\ReorderProductMediaAction;
use App\Actions\SetPrimaryProductMediaAction;
use App\Actions\UploadProductMediaAction;
use App\Data\MediaItemData;
use App\Http\Requests\Admin\ReorderMediaRequest;
use App\Http\Requests\Admin\UploadProductMediaRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class AdminProductMediaWebController extends Controller
{
    public function upload(
        UploadProductMediaRequest $request,
        Product $product,
        UploadProductMediaAction $action,
    ): RedirectResponse|JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $isPrimary = $request->boolean('is_primary');
        /** @var string|null $name */
        $name = $request->input('name');

        $media = $action->execute($product, $file, $isPrimary, $name);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم رفع الصورة بنجاح.',
                'media' => MediaItemData::fromMedia($media),
            ]);
        }

        return back()->with('success', 'تم رفع الصورة بنجاح.');
    }

    public function destroy(
        Request $request,
        Product $product,
        int $media,
        DeleteProductMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $action->execute($product, $media);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الصورة بنجاح.',
            ]);
        }

        return back()->with('success', 'تم حذف الصورة بنجاح.');
    }

    public function setPrimary(
        Request $request,
        Product $product,
        int $media,
        SetPrimaryProductMediaAction $action,
    ): RedirectResponse|JsonResponse {
        $action->execute($product, $media);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تعيين الصورة كصورة رئيسية.',
            ]);
        }

        return back()->with('success', 'تم تعيين الصورة كصورة رئيسية.');
    }

    public function reorder(
        ReorderMediaRequest $request,
        Product $product,
        ReorderProductMediaAction $action,
    ): RedirectResponse|JsonResponse {
        /** @var array<int> $mediaIds */
        $mediaIds = $request->validated('media_ids');
        $action->execute($product, $mediaIds);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ ترتيب الصور بنجاح.',
            ]);
        }

        return back()->with('success', 'تم حفظ ترتيب الصور بنجاح.');
    }
}
