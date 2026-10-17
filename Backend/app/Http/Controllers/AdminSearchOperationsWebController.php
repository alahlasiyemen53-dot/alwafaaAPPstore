<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Search\ProductSearchInterface;
use App\Contracts\Search\VisualProductSearchInterface;
use App\DTOs\Search\ProductSearchQuery;
use App\Models\MediaEmbedding;
use App\Services\Search\ProductIndexingService;
use App\Services\Search\SearchReconciliationService;
use App\Services\Search\VisualIndexingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminSearchOperationsWebController extends Controller
{
    public function __construct(
        private readonly SearchReconciliationService $reconciliationService,
        private readonly ProductIndexingService $productIndexingService,
        private readonly VisualIndexingService $visualIndexingService,
        private readonly ProductSearchInterface $productSearch,
        private readonly VisualProductSearchInterface $visualSearch,
    ) {}

    public function index(Request $request): View
    {
        $stats = $this->reconciliationService->getIndexStats();

        $recentEmbeddings = MediaEmbedding::query()
            ->with(['product', 'media'])
            ->latest('id')
            ->take(15)
            ->get();

        $testResults = null;
        $testType = null;
        $testLatencyMs = null;

        $textQuery = $request->query('q');
        if (! empty($textQuery)) {
            $testType = 'text';
            $queryDto = new ProductSearchQuery(
                query: (string) $textQuery,
                sortBy: (string) $request->query('sort', 'relevance'),
                page: 1,
                perPage: 12,
            );
            $start = microtime(true);
            $testResults = $this->productSearch->search($queryDto);
            $testLatencyMs = round((microtime(true) - $start) * 1000, 2);
        }

        return view('admin.search.index', [
            'stats' => $stats,
            'recentEmbeddings' => $recentEmbeddings,
            'testResults' => $testResults,
            'testType' => $testType,
            'testLatencyMs' => $testLatencyMs,
            'currentQuery' => $textQuery,
        ]);
    }

    public function testVisual(Request $request): View
    {
        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $file = $request->file('image');
        $start = microtime(true);
        $visualResult = $this->visualSearch->searchByImage($file->getRealPath(), limit: 12, minSimilarity: 0.50);
        $testLatencyMs = round((microtime(true) - $start) * 1000, 2);

        $stats = $this->reconciliationService->getIndexStats();
        $recentEmbeddings = MediaEmbedding::query()->with(['product', 'media'])->latest('id')->take(15)->get();

        return view('admin.search.index', [
            'stats' => $stats,
            'recentEmbeddings' => $recentEmbeddings,
            'testResults' => $visualResult,
            'testType' => 'visual',
            'testLatencyMs' => $testLatencyMs,
            'currentQuery' => null,
        ]);
    }

    public function reindexText(): RedirectResponse
    {
        try {
            $count = $this->productIndexingService->indexAll();

            return back()->with('success', "تمت إعادة فهرسة {$count} منتج بنجاح في محرك البحث.");
        } catch (Exception $e) {
            return back()->with('error', 'فشلت إعادة فهرسة الكتالوج النصي: '.$e->getMessage());
        }
    }

    public function reconcileText(): RedirectResponse
    {
        try {
            $report = $this->reconciliationService->reconcileTextCatalog();

            return back()->with('success', "تمت مطابقة الفهرس النصي. (تمت فهرسة: {$report['indexed']}، أخطاء: {$report['errors']})");
        } catch (Exception $e) {
            return back()->with('error', 'فشلت مطابقة الفهرس النصي: '.$e->getMessage());
        }
    }

    public function reindexVisual(): RedirectResponse
    {
        try {
            $count = $this->visualIndexingService->indexAll();

            return back()->with('success', "تم بنجاح توليد وفهرسة {$count} متجه بصري.");
        } catch (Exception $e) {
            return back()->with('error', 'فشلت إعادة فهرسة التضمينات البصرية: '.$e->getMessage());
        }
    }

    public function reconcileVisual(): RedirectResponse
    {
        try {
            $report = $this->reconciliationService->reconcileVisualEmbeddings();

            return back()->with('success', "تمت مطابقة المتجهات البصرية. (مفهرسة: {$report['indexed']}، منظفة: {$report['cleaned']}، أخطاء: {$report['errors']})");
        } catch (Exception $e) {
            return back()->with('error', 'فشلت مطابقة المتجهات البصرية: '.$e->getMessage());
        }
    }
}
