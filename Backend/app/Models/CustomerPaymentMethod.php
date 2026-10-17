<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\PaymentFlowType;
use App\Enums\PaymentProviderCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CustomerPaymentMethod extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'code',
        'provider_code',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'instructions_ar',
        'instructions_en',
        'channel',
        'flow_type',
        'sort_order',
        'is_active',
        'electronic_wallet_account_id',
    ];

    protected $casts = [
        'provider_code' => PaymentProviderCode::class,
        'channel' => PaymentChannel::class,
        'flow_type' => PaymentFlowType::class,
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(128)->height(128)->nonQueued();
    }

    public function getLogoUrl(?string $conversion = null): ?string
    {
        $url = $conversion
            ? $this->getFirstMediaUrl('logo', $conversion)
            : $this->getFirstMediaUrl('logo');

        return $url !== '' ? $url : null;
    }

    /** @return BelongsTo<ElectronicWalletAccount, $this> */
    public function electronicWalletAccount(): BelongsTo
    {
        return $this->belongsTo(ElectronicWalletAccount::class, 'electronic_wallet_account_id');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * @return array{code: string, name_ar: string, name_en: string|null}
     */
    public static function presentationForCode(?string $code): array
    {
        static $cache = [];

        $normalized = $code !== null && $code !== '' ? $code : 'cod';

        if (isset($cache[$normalized])) {
            return $cache[$normalized];
        }

        if ($normalized === 'cod') {
            return $cache[$normalized] = [
                'code' => 'cod',
                'name_ar' => 'الدفع عند الاستلام (COD)',
                'name_en' => 'Cash on Delivery',
            ];
        }

        $method = self::query()->where('code', $normalized)->first();
        if ($method === null) {
            return $cache[$normalized] = [
                'code' => $normalized,
                'name_ar' => $normalized,
                'name_en' => null,
            ];
        }

        return $cache[$normalized] = [
            'code' => $method->code,
            'name_ar' => $method->name_ar,
            'name_en' => $method->name_en,
        ];
    }
}
