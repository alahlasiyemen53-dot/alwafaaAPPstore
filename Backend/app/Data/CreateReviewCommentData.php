<?php

namespace App\Data;

final readonly class CreateReviewCommentData
{
    public function __construct(
        public string $body,
    ) {}

    /**
     * @param  array{body: string}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            body: trim(strip_tags($validated['body'])),
        );
    }
}
