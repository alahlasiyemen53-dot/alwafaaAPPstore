<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CalculateDriverCodOutstandingAction;
use App\Actions\CreateDraftDriverCodSettlementAction;
use App\Actions\GetEligibleCodDeliveriesAction;
use App\Actions\PostDriverCodSettlementAction;
use App\Actions\ReverseDriverCodSettlementAction;
use App\Enums\DriverCodSettlementStatus;
use App\Http\Requests\PostDriverCodSettlementRequest;
use App\Http\Requests\ReverseDriverCodSettlementRequest;
use App\Http\Requests\StoreDriverCodSettlementRequest;
use App\Models\BankAccount;
use App\Models\Cashbox;
use App\Models\Currency;
use App\Models\Driver;
use App\Models\DriverCodSettlement;
use App\Models\ElectronicWalletAccount;
use App\Services\DriverCodReconciliationService;
use App\Services\DriverCodStatementService;
use App\Support\DateFilterNormalizer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminDriverCodSettlementWebController extends Controller
{
    public function __construct(
        private readonly CreateDraftDriverCodSettlementAction $createDraftAction,
        private readonly PostDriverCodSettlementAction $postAction,
        private readonly ReverseDriverCodSettlementAction $reverseAction,
        private readonly GetEligibleCodDeliveriesAction $getEligibleDeliveriesAction,
        private readonly CalculateDriverCodOutstandingAction $calculateOutstandingAction,
        private readonly DriverCodStatementService $statementService,
        private readonly DriverCodReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.driver_cod_settlements.view');

        $query = DriverCodSettlement::query()
            ->with(['driver', 'currency', 'treasuryAccount', 'journalEntry'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('settlement_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('driver', fn ($dq) => $dq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->input('currency_id'));
        }

        if ($request->filled('from_date')) {
            $normFrom = DateFilterNormalizer::normalize($request->input('from_date'));
            if ($normFrom) {
                $query->whereDate('created_at', '>=', $normFrom);
            }
        }

        if ($request->filled('to_date')) {
            $normTo = DateFilterNormalizer::normalize($request->input('to_date'));
            if ($normTo) {
                $query->whereDate('created_at', '<=', $normTo);
            }
        }

        $settlements = $query->paginate(15)->withQueryString();

        $drivers = Driver::query()->where('is_active', true)->orderBy('name')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        $totalPostedAmount = (float) DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Posted)->sum('total_settled');
        $countDraft = DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Draft)->count();
        $countPosted = DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Posted)->count();
        $countReversed = DriverCodSettlement::query()->where('status', DriverCodSettlementStatus::Reversed)->count();

        return view('admin.finance.cod-settlements.index', [
            'settlements' => $settlements,
            'drivers' => $drivers,
            'currencies' => $currencies,
            'statuses' => DriverCodSettlementStatus::cases(),
            'totalPostedAmount' => $totalPostedAmount,
            'countDraft' => $countDraft,
            'countPosted' => $countPosted,
            'countReversed' => $countReversed,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePermission('admin.driver_cod_settlements.create');

        $drivers = Driver::query()->where('is_active', true)->orderBy('name')->get();
        $currencies = Currency::query()->where('is_active', true)->get();
        $cashboxes = Cashbox::query()->where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();
        $bankAccounts = BankAccount::query()->where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();
        $electronicWallets = ElectronicWalletAccount::query()->where('is_active', true)->with(['currency', 'currencyAccounts.currency'])->get();

        return view('admin.finance.cod-settlements.create', [
            'drivers' => $drivers,
            'currencies' => $currencies,
            'cashboxes' => $cashboxes,
            'bankAccounts' => $bankAccounts,
            'electronicWallets' => $electronicWallets,
            'preselectedDriverId' => $request->input('driver_id'),
            'preselectedCurrencyId' => $request->input('currency_id'),
        ]);
    }

    public function eligibleDeliveries(Request $request): JsonResponse
    {
        $this->authorizePermission('admin.driver_cod_settlements.create');

        $driverId = (int) $request->input('driver_id');
        $currencyId = (int) $request->input('currency_id');

        if (! $driverId || ! $currencyId) {
            return response()->json([
                'status' => 'error',
                'message' => 'يرجى تحديد المندوب والعملة أولاً.',
                'deliveries' => [],
            ], 422);
        }

        $deliveries = $this->getEligibleDeliveriesAction->execute($driverId, $currencyId);
        $outstanding = $this->calculateOutstandingAction->execute($driverId, $currencyId);

        return response()->json([
            'status' => 'success',
            'deliveries' => $deliveries,
            'outstanding' => $outstanding,
        ]);
    }

    public function store(StoreDriverCodSettlementRequest $request): RedirectResponse
    {
        try {
            $data = $request->toDTO();
            $userId = (int) auth()->id();

            $settlement = $this->createDraftAction->execute($data, $userId);

            if ($data->postDirectly) {
                $settlement = $this->postAction->execute($settlement, $userId);
                $message = sprintf('تم إنشاء وترحيل تسوية التحصيل COD رقم (%s) بنجاح وإثبات القيد المحاسبي.', $settlement->settlement_number);
            } else {
                $message = sprintf('تم حفظ مسودة تسوية التحصيل COD رقم (%s) بنجاح.', $settlement->settlement_number);
            }

            return redirect()
                ->route('admin.finance.cod-settlements.show', $settlement)
                ->with('success', $message);
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function show(DriverCodSettlement $driverCodSettlement): View
    {
        $this->authorizePermission('admin.driver_cod_settlements.view');

        $settlement = $driverCodSettlement->load([
            'driver',
            'currency',
            'treasuryAccount',
            'items.delivery.order.customer',
            'journalEntry.lines.account',
            'postedBy',
            'reversedBy',
            'creator',
        ]);

        return view('admin.finance.cod-settlements.show', [
            'settlement' => $settlement,
        ]);
    }

    public function post(PostDriverCodSettlementRequest $request, DriverCodSettlement $driverCodSettlement): RedirectResponse
    {
        try {
            $userId = (int) auth()->id();
            $settlement = $this->postAction->execute($driverCodSettlement, $userId);

            return redirect()
                ->route('admin.finance.cod-settlements.show', $settlement)
                ->with('success', sprintf('تم اعتماد وترحيل التسوية (%s) وإيداع المبالغ بالخزينة بنجاح.', $settlement->settlement_number));
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reverse(ReverseDriverCodSettlementRequest $request, DriverCodSettlement $driverCodSettlement): RedirectResponse
    {
        try {
            $userId = (int) auth()->id();
            $reason = (string) $request->input('reason');
            $settlement = $this->reverseAction->execute($driverCodSettlement, $reason, $userId);

            return redirect()
                ->route('admin.finance.cod-settlements.show', $settlement)
                ->with('warning', sprintf('تم عكس التسوية (%s) وإلغاء القيد المحاسبي واستعادة عهدة المندوب بنجاح.', $settlement->settlement_number));
        } catch (DomainException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function statements(Request $request): View
    {
        $this->authorizePermission('admin.driver_cod_statements.view');

        $drivers = Driver::query()->orderBy('name')->get();
        $currencies = Currency::query()->where('is_active', true)->get();

        $statement = null;
        if ($request->filled('driver_id')) {
            $driverId = (int) $request->input('driver_id');
            $currencyId = $request->filled('currency_id') ? (int) $request->input('currency_id') : null;
            $startDate = DateFilterNormalizer::normalize($request->input('from_date'));
            $endDate = DateFilterNormalizer::normalize($request->input('to_date'));

            $statement = $this->statementService->generate($driverId, $currencyId, $startDate, $endDate);
        }

        return view('admin.finance.cod-settlements.statements', [
            'drivers' => $drivers,
            'currencies' => $currencies,
            'statement' => $statement,
        ]);
    }

    public function reconciliation(): View
    {
        $this->authorizePermission('admin.driver_cod_reconciliation.view');

        $reconciliation = $this->reconciliationService->reconcile();

        return view('admin.finance.cod-settlements.reconciliation', [
            'reconciliation' => $reconciliation,
        ]);
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->check() || ! auth()->user()->hasPermission($permission)) {
            abort(403);
        }
    }
}
