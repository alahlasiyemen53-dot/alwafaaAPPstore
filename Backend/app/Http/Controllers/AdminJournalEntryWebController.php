<?php

namespace App\Http\Controllers;

use App\Actions\CreateDraftJournalEntryAction;
use App\Actions\PostJournalEntryAction;
use App\Actions\ReverseJournalEntryAction;
use App\Actions\UpdateDraftJournalEntryAction;
use App\Enums\JournalEntryStatus;
use App\Http\Requests\ReverseJournalEntryRequest;
use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Support\DateFilterNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminJournalEntryWebController extends Controller
{
    public function __construct(
        private readonly CreateDraftJournalEntryAction $createDraftAction,
        private readonly UpdateDraftJournalEntryAction $updateDraftAction,
        private readonly PostJournalEntryAction $postAction,
        private readonly ReverseJournalEntryAction $reverseAction,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('admin.journal_entries.view');

        $query = JournalEntry::query()
            ->with(['accountingPeriod', 'currency', 'postedBy', 'lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('entry_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('period_id')) {
            $query->where('accounting_period_id', $request->input('period_id'));
        }

        if ($request->filled('from_date')) {
            $normFrom = DateFilterNormalizer::normalize($request->input('from_date'));
            if ($normFrom) {
                $query->where('entry_date', '>=', $normFrom);
            }
        }

        if ($request->filled('to_date')) {
            $normTo = DateFilterNormalizer::normalize($request->input('to_date'));
            if ($normTo) {
                $query->where('entry_date', '<=', $normTo);
            }
        }

        $entries = $query->paginate(20)->withQueryString();

        $metrics = [
            'total' => JournalEntry::count(),
            'draft' => JournalEntry::where('status', JournalEntryStatus::Draft)->count(),
            'posted' => JournalEntry::where('status', JournalEntryStatus::Posted)->count(),
            'reversed' => JournalEntry::where('status', JournalEntryStatus::Reversed)->count(),
        ];

        $periods = AccountingPeriod::query()->orderByDesc('starts_at')->get();

        return view('admin.finance.journal_entries.index', [
            'entries' => $entries,
            'metrics' => $metrics,
            'periods' => $periods,
            'filters' => $request->only(['search', 'status', 'period_id', 'from_date', 'to_date']),
            'statuses' => JournalEntryStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('admin.journal_entries.create');

        $periods = AccountingPeriod::open()->orderByDesc('starts_at')->get();
        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = Account::where('is_active', true)->where('is_postable', true)->orderBy('code')->get();

        return view('admin.finance.journal_entries.create', [
            'periods' => $periods,
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $entry = $this->createDraftAction->execute($request->toDto());

        return redirect()
            ->route('admin.finance.journal-entries.show', $entry)
            ->with('success', "تم حفظ مسودة القيد اليومي رقم '{$entry->entry_number}' بنجاح.");
    }

    public function show(JournalEntry $journalEntry): View
    {
        $this->authorizePermission('admin.journal_entries.view');

        $journalEntry->load([
            'accountingPeriod',
            'currency',
            'lines.account',
            'lines.transactionCurrency',
            'lines.party',
            'postedBy',
            'reversedBy',
            'reversalOf',
            'reversalEntry',
        ]);

        $openPeriods = AccountingPeriod::open()->orderByDesc('starts_at')->get();

        return view('admin.finance.journal_entries.show', [
            'entry' => $journalEntry,
            'openPeriods' => $openPeriods,
        ]);
    }

    public function edit(JournalEntry $journalEntry): View
    {
        $this->authorizePermission('admin.journal_entries.update');

        if (! $journalEntry->isDraft()) {
            abort(403, 'لا يمكن تعديل قيد مرحل أو معكوس.');
        }

        $journalEntry->load(['lines.account']);
        $periods = AccountingPeriod::open()->orderByDesc('starts_at')->get();
        $currencies = Currency::where('is_active', true)->orderByDesc('is_default')->get();
        $accounts = Account::where('is_active', true)->where('is_postable', true)->orderBy('code')->get();

        return view('admin.finance.journal_entries.edit', [
            'entry' => $journalEntry,
            'periods' => $periods,
            'currencies' => $currencies,
            'accounts' => $accounts,
        ]);
    }

    public function update(StoreJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $entry = $this->updateDraftAction->execute($journalEntry, $request->toDto());

        return redirect()
            ->route('admin.finance.journal-entries.show', $entry)
            ->with('success', "تم تحديث مسودة القيد رقم '{$entry->entry_number}' بنجاح.");
    }

    public function post(JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission('admin.journal_entries.post');

        $posted = $this->postAction->execute($journalEntry);

        return redirect()
            ->route('admin.finance.journal-entries.show', $posted)
            ->with('success', "تم ترحيل القيد المحاسبي رقم '{$posted->entry_number}' بنجاح، وأصبح غير قابل للتعديل المباشر.");
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->authorizePermission('admin.journal_entries.reverse');

        $reversal = $this->reverseAction->execute($journalEntry, $request->toDto());

        return redirect()
            ->route('admin.finance.journal-entries.show', $reversal)
            ->with('success', "تم عكس القيد الأصلي وإنشاء القيد العكسي رقم '{$reversal->entry_number}' بنجاح.");
    }

    private function authorizePermission(string $permission): void
    {
        if (! auth()->user()?->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بإجراء هذه العملية المحاسبية.');
        }
    }
}
