@extends('layouts.admin')
@section('title', 'الفترات المحاسبية | لمّة')
@section('page-title', 'الفترات المحاسبية')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header border-0">
                <h5 class="card-title mb-0">سجل الفترات المالية المحاسبية</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>اسم الفترة</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ النهاية</th>
                                <th>القيود المسجلة</th>
                                <th>الحالة</th>
                                <th>تاريخ الإغلاق</th>
                                <th class="text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($periods as $period)
                                <tr>
                                    <td>
                                        <span class="fw-semibold text-dark">{{ $period->name }}</span>
                                    </td>
                                    <td>{{ $period->starts_at->format('d-m-Y') }}</td>
                                    <td>{{ $period->ends_at->format('d-m-Y') }}</td>
                                    <td>
                                        <span class="badge bg-light text-body border">{{ number_format($period->journal_entries_count) }} قيد</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $period->status->badgeClass() }}">{{ $period->status->label() }}</span>
                                    </td>
                                    <td>
                                        @if($period->closed_at)
                                            <small class="text-muted">{{ $period->closed_at->format('d-m-Y H:i') }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($period->isOpen() && auth()->user()->hasPermission('admin.accounting_periods.close'))
                                            <form method="POST" action="{{ route('admin.finance.periods.close', $period) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في إغلاق هذه الفترة المحاسبية؟ بعد الإغلاق لن يمكن تسجيل أو ترحيل أي قيود ضمن هذا النطاق الزمني.');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-subtle-danger" title="إغلاق الفترة المالية">
                                                    <i class="ri-lock-line me-1"></i> إغلاق الفترة
                                                </button>
                                            </form>
                                        @elseif($period->isClosed())
                                            <span class="text-muted small"><i class="ri-lock-2-line"></i> مغلقة نهائياً</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">لا توجد فترات محاسبية مسجلة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $periods->links() }}
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->hasPermission('admin.accounting_periods.create'))
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header border-0">
                    <h5 class="card-title mb-0">إضافة فترة محاسبية جديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.finance.periods.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label required">اسم الفترة</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="مثال: الربع الأول 2026 أو سنة 2027" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">تاريخ البداية</label>
                            <input type="text" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ old('starts_at') }}" placeholder="يوم-شهر-سنة" required>
                            @error('starts_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">تاريخ النهاية</label>
                            <input type="text" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ old('ends_at') }}" placeholder="يوم-شهر-سنة" required>
                            @error('ends_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ri-add-line me-1"></i> حفظ الفترة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
