<?php

namespace App\Http\Resources\Api\V1\Reviews;

use App\Models\ReviewComment;
use App\Support\CustomerDisplayName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReviewComment
 */
final class ReviewCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewComment $comment */
        $comment = $this->resource;

        $isOfficial = $comment->isOfficialAdminReply();

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'is_official_admin_reply' => $isOfficial,
            'author' => [
                'display_name' => $isOfficial
                    ? 'إدارة لمّة'
                    : CustomerDisplayName::from($comment->customer),
                'type' => $isOfficial ? 'admin' : 'customer',
            ],
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
