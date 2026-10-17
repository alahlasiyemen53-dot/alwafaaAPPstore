<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class Supplier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'secondary_phone',
        'email',
        'address',
        'tax_number',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return HasMany<PurchaseInvoice, $this> */
    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<OrderSourcingRequirement, $this> */
    public function sourcingRequirements(): HasMany
    {
        return $this->hasMany(OrderSourcingRequirement::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<SupplierLedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    /**
     * Calculate authoritative supplier balances grouped by currency.
     * Credit entries increase what we owe the supplier.
     * Debit entries decrease what we owe.
     *
     * @return array<string, array{currency: Currency, balance: float, formatted: string}>
     */
    public function getBalancesByCurrency(): array
    {
        $rawBalances = $this->ledgerEntries()
            ->select([
                'currency_id',
                DB::raw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as balance"),
            ])
            ->groupBy('currency_id')
            ->get();

        $currencies = Currency::query()
            ->whereIn('id', $rawBalances->pluck('currency_id'))
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($rawBalances as $row) {
            $currency = $currencies->get($row->currency_id);
            if ($currency) {
                $balance = (float) $row->balance;
                $result[$currency->code] = [
                    'currency' => $currency,
                    'balance' => $balance,
                    'formatted' => number_format($balance, $currency->decimal_places).' '.($currency->symbol ?? $currency->code),
                ];
            }
        }

        return $result;
    }
}
