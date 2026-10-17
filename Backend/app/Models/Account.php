<?php

namespace App\Models;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'parent_id',
        'type',
        'normal_balance',
        'is_postable',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'normal_balance' => AccountNormalBalance::class,
        'is_postable' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if this account is a descendant of the given account ID (cycle prevention).
     */
    public function isDescendantOf(int $potentialAncestorId): bool
    {
        $current = $this;
        while ($current->parent_id !== null) {
            if ($current->parent_id === $potentialAncestorId) {
                return true;
            }
            $current = $current->parent;
            if ($current === null) {
                break;
            }
        }

        return false;
    }

    /**
     * Calculate authoritative account balance from POSTED journal lines in base currency.
     */
    public function calculateBalance(?Carbon $asOf = null): string
    {
        $query = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->id)
            ->whereIn('journal_entries.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value]);

        if ($asOf !== null) {
            $query->where('journal_entries.entry_date', '<=', $asOf->toDateString());
        }

        $totals = $query->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit, COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit')
            ->first();

        $debit = (string) ($totals->total_debit ?? '0');
        $credit = (string) ($totals->total_credit ?? '0');

        if ($this->normal_balance === AccountNormalBalance::Debit) {
            return bcsub($debit, $credit, 4);
        }

        return bcsub($credit, $debit, 4);
    }
}
