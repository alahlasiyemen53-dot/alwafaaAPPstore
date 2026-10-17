<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Customer;

use App\Actions\MergeGuestSearchHistoryAction;
use App\Actions\RecordSearchHistoryAction;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\MergeGuestSearchHistoryRequest;
use App\Http\Requests\Api\V1\Customer\RecordSearchHistoryRequest;
use App\Models\Customer;
use App\Models\CustomerSearchHistory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerSearchHistoryController extends Controller
{
    public function __construct(
        private readonly RecordSearchHistoryAction $recordAction,
        private readonly MergeGuestSearchHistoryAction $mergeGuest,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        return ApiResponse::success(
            ['items' => $this->itemsFor($customer)],
            'تم استرجاع سجل البحث.'
        );
    }

    public function store(RecordSearchHistoryRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        $record = $this->recordAction->execute($customer, (string) $request->validated('query'));

        if ($record === null) {
            return ApiResponse::success(null, 'تم تجاهل استعلام البحث القصير.');
        }

        return ApiResponse::success([
            'id' => $record->id,
            'query' => $record->query,
            'last_searched_at' => $record->last_searched_at?->toIso8601String(),
        ], 'تم حفظ البحث.');
    }

    public function destroy(Request $request, int $item): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        $deleted = CustomerSearchHistory::query()
            ->where('customer_id', $customer->id)
            ->whereKey($item)
            ->delete();

        if ($deleted === 0) {
            return ApiResponse::error(
                ApiErrorCode::ResourceNotFound->defaultMessage(),
                ApiErrorCode::ResourceNotFound,
                404
            );
        }

        return ApiResponse::success(['id' => $item], 'تم حذف عنصر سجل البحث.');
    }

    public function clear(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');

        CustomerSearchHistory::query()
            ->where('customer_id', $customer->id)
            ->delete();

        return ApiResponse::success(null, 'تم مسح سجل البحث.');
    }

    public function merge(MergeGuestSearchHistoryRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer_jwt');
        /** @var list<string> $queries */
        $queries = array_values(array_map(
            static fn (mixed $q): string => (string) $q,
            $request->validated('queries'),
        ));

        $this->mergeGuest->execute($customer, $queries);

        return ApiResponse::success(
            ['items' => $this->itemsFor($customer)],
            'تمت مزامنة سجل البحث.'
        );
    }

    /** @return list<array{id: int, query: string, last_searched_at: ?string}> */
    private function itemsFor(Customer $customer): array
    {
        $limit = max(1, (int) config('customer_experience.search_history_limit', 15));

        return CustomerSearchHistory::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('last_searched_at')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerSearchHistory $row): array => [
                'id' => $row->id,
                'query' => $row->query,
                'last_searched_at' => $row->last_searched_at?->toIso8601String(),
            ])
            ->all();
    }
}
