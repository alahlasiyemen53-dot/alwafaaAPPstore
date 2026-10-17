@php($statusPresentation = match($status) {
    \App\Enums\ProductStatus::Active => ['نشط', 'bg-success-subtle text-success', 'bg-success'],
    \App\Enums\ProductStatus::Inactive => ['معطل', 'bg-secondary-subtle text-secondary', 'bg-secondary'],
    \App\Enums\ProductStatus::Draft => ['مسودة', 'bg-warning-subtle text-warning', 'bg-warning'],
})
<span class="badge {{ $statusPresentation[1] }}"><span class="status-dot {{ $statusPresentation[2] }}"></span>{{ $statusPresentation[0] }}</span>
