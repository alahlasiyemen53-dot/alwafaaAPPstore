<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $media_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property string $status
 * @property string $provider
 * @property string $model_version
 * @property string $embedding_version
 * @property int $vector_dimension
 * @property string $vector_id
 * @property array<int, float>|null $embedding_vector
 * @property int $retry_count
 * @property string|null $last_error
 * @property CarbonInterface|null $indexed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Media $media
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 */
final class MediaEmbedding extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_INDEXED = 'indexed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'media_id',
        'product_id',
        'product_variant_id',
        'status',
        'provider',
        'model_version',
        'embedding_version',
        'vector_dimension',
        'vector_id',
        'embedding_vector',
        'retry_count',
        'last_error',
        'indexed_at',
    ];

    protected $casts = [
        'embedding_vector' => 'array',
        'vector_dimension' => 'integer',
        'retry_count' => 'integer',
        'indexed_at' => 'datetime',
    ];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
