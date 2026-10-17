<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * Runs Eloquent's firstOrCreate() but survives a concurrent duplicate-key race:
 * two requests racing to create the same "first time" row (e.g. a customer's
 * loyalty/wallet account) will have one succeed and one hit the unique
 * constraint. On MySQL/InnoDB a duplicate-key error does not abort the
 * enclosing transaction, so we simply re-read the winning row and continue.
 *
 * Must be called from inside the same DB::transaction() that will go on to
 * lockForUpdate() the resolved row.
 */
final class RaceSafeFirstOrCreate
{
    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    public static function resolve(string $model, array $attributes, array $values = []): Model
    {
        try {
            return $model::query()->firstOrCreate($attributes, $values);
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw $exception;
            }

            /** @var Builder<TModel> $query */
            $query = $model::query();
            foreach ($attributes as $column => $value) {
                $query->where($column, $value);
            }

            return $query->firstOrFail();
        }
    }
}
