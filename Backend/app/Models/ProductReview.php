<?php

namespace App\Models;

use App\Enums\ProductReviewStatus;
use App\Enums\ReviewCommentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductReview extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'customer_id',
        'product_id',
        'order_id',
        'rating',
        'title',
        'body',
        'comment',
        'status',
        'is_verified_purchase',
        'moderated_by',
        'moderator_id',
        'moderated_at',
        'moderation_reason',
    ];

    protected $casts = [
        'status' => ProductReviewStatus::class,
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    public function getCommentAttribute(): ?string
    {
        return $this->body;
    }

    public function setCommentAttribute(?string $value): void
    {
        $this->attributes['body'] = $value;
    }

    public function getModeratorIdAttribute(): ?int
    {
        return $this->moderated_by;
    }

    public function setModeratorIdAttribute(?int $value): void
    {
        $this->attributes['moderated_by'] = $value;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** @return HasMany<ReviewComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'review_id');
    }

    /** @return HasMany<ReviewComment, $this> */
    public function visibleComments(): HasMany
    {
        return $this->comments()->where('status', ReviewCommentStatus::Approved);
    }

    /** @param Builder<$this> $query */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ProductReviewStatus::Approved);
    }

    /** @param Builder<$this> $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ProductReviewStatus::Pending);
    }

    public function isApproved(): bool
    {
        return $this->status === ProductReviewStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === ProductReviewStatus::Pending;
    }

    public function isRejected(): bool
    {
        return $this->status === ProductReviewStatus::Rejected;
    }

    public function isHidden(): bool
    {
        return $this->status === ProductReviewStatus::Hidden;
    }
}
