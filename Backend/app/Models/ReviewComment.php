<?php

namespace App\Models;

use App\Enums\ReviewCommentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReviewComment extends Model
{
    use HasFactory;

    protected $table = 'review_comments';

    protected $fillable = [
        'review_id',
        'customer_id',
        'admin_user_id',
        'body',
        'status',
    ];

    protected $casts = [
        'status' => ReviewCommentStatus::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (ReviewComment $comment): void {
            $hasCustomer = $comment->customer_id !== null;
            $hasAdmin = $comment->admin_user_id !== null;

            if ($hasCustomer === $hasAdmin) {
                throw new \InvalidArgumentException(
                    'Comment author must be exactly one of customer_id or admin_user_id.'
                );
            }
        });
    }

    /** @return BelongsTo<ProductReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'review_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isOfficialAdminReply(): bool
    {
        return $this->admin_user_id !== null;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status->isPubliclyVisible();
    }

    /** @param Builder<$this> $query */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReviewCommentStatus::Approved);
    }

    /** @param Builder<$this> $query */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->approved();
    }
}
