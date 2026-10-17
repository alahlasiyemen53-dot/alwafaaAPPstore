<?php

namespace App\Http\Controllers;

use App\Actions\ImportProductWorkbookAction;
use App\Http\Requests\Admin\PreviewProductImportRequest;
use App\Services\ProductImportValidationService;
use App\Services\ProductImportWorkbookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AdminProductImportWebController extends Controller
{
    public function create(): View
    {
        return view('admin.products.import');
    }

    public function template(ProductImportWorkbookService $workbook): BinaryFileResponse
    {
        $filename = 'products_import_template.xlsx';
        $path = storage_path('app/private/product-imports/'.$filename);
        Storage::disk('local')->makeDirectory('product-imports');
        $workbook->writeTemplate($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(
        PreviewProductImportRequest $request,
        ProductImportWorkbookService $workbook,
        ProductImportValidationService $validator,
    ): RedirectResponse {
        $file = $request->file('workbook');
        $path = $file->storeAs('product-imports', Str::uuid().'.xlsx', 'local');
        try {
            $preview = $validator->validate($workbook->rows(Storage::disk('local')->path($path)));
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        session()->forget('product_import');
        session()->put('product_import', [
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            'actor_id' => auth()->id(),
        ]);

        return redirect()->route('admin.products.import.create')->with('product_import_preview', [
            'total' => count($preview['rows']) + count($preview['errors']),
            'valid' => count($preview['rows']),
            'invalid' => count($preview['errors']),
            'products' => $preview['products'],
            'warnings' => $preview['warnings'],
            'errors' => $preview['errors'],
        ]);
    }

    public function confirm(
        ProductImportWorkbookService $workbook,
        ProductImportValidationService $validator,
        ImportProductWorkbookAction $action,
    ): RedirectResponse {
        $pending = session('product_import');
        if (! is_array($pending) || ! isset($pending['path'], $pending['hash'], $pending['filename'], $pending['actor_id']) || $pending['actor_id'] !== auth()->id() || ! Storage::disk('local')->exists($pending['path'])) {
            return redirect()->route('admin.products.import.create')->with('error', 'لا توجد معاينة صالحة للاستيراد. ارفع الملف وافحصه مجدداً.');
        }

        $path = Storage::disk('local')->path($pending['path']);
        if (! hash_equals($pending['hash'], hash_file('sha256', $path))) {
            Storage::disk('local')->delete($pending['path']);
            session()->forget('product_import');

            return redirect()->route('admin.products.import.create')->with('error', 'تغير ملف الاستيراد؛ أعد رفعه وفحصه.');
        }

        $preview = $validator->validate($workbook->rows($path));
        if ($preview['errors'] !== []) {
            Storage::disk('local')->delete($pending['path']);
            session()->forget('product_import');

            return redirect()->route('admin.products.import.create')->with('error', 'تغيرت بيانات النظام وأصبح الملف غير صالح. افحص الملف مجدداً.')
                ->with('product_import_preview', ['total' => count($preview['rows']) + count($preview['errors']), 'valid' => count($preview['rows']), 'invalid' => count($preview['errors']), 'products' => $preview['products'], 'warnings' => $preview['warnings'], 'errors' => $preview['errors']]);
        }

        try {
            $report = $action->execute($preview['rows'], $pending['filename']);
        } finally {
            Storage::disk('local')->delete($pending['path']);
            session()->forget('product_import');
        }

        return redirect()->route('admin.products.index')->with('success', "تم استيراد {$report['products']} منتج و{$report['variants']} متغير بنجاح.");
    }
}
