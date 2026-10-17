<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SlugGenerator
{
    /**
     * Generate a unique slug for the given model and title.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function generate(
        string $title,
        string $modelClass,
        ?int $excludeId = null,
        string $column = 'slug',
        int $maxLength = 220,
    ): string {
        $baseSlug = $this->createBaseSlug($title, $maxLength);

        $candidate = $baseSlug;
        $counter = 1;

        while ($this->exists($modelClass, $column, $candidate, $excludeId)) {
            $counter++;
            if ($counter > 100) {
                // If hundreds of duplicates exist, append a unique random string
                $candidate = sprintf('%s-%s', Str::limit($baseSlug, $maxLength - 9, ''), strtolower(Str::random(8)));
                break;
            }
            $candidate = sprintf('%s-%d', $baseSlug, $counter);
        }

        return $candidate;
    }

    /**
     * Create the clean base slug.
     */
    public function createBaseSlug(string $title, int $maxLength = 220): string
    {
        $clean = trim($title);
        $slug = Str::slug($clean);

        if ($slug === '') {
            // If Latin transliteration resulted in empty (e.g. non-latin languages), allow unicode slug
            $slug = Str::slug($clean, '-', null);
        }

        if ($slug === '') {
            $slug = 'item';
        }

        return Str::limit($slug, $maxLength, '');
    }

    /**
     * Check if a candidate slug exists in the database.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function exists(string $modelClass, string $column, string $candidate, ?int $excludeId): bool
    {
        $query = $modelClass::query()->where($column, $candidate);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
