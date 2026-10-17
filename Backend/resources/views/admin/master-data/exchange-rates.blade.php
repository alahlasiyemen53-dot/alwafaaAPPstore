@extends('layouts.admin')
@section('title', 'أسعار الصرف | لمّة')
@section('page-title', 'أسعار الصرف')
@section('content')
@php($editingRate = $editingRate ?? null)

<div class="row">
    <div class="col-12 mb-3">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center mb-0">
            <i class="ri-information-line fs-24 me-2 text-primary"></i>
            <div>
                <span class="fw-bold">العملة الأساسية الحالية للنظام:</span>
                <span class="badge bg-primary fs-12 ms-1">{{ $baseCurrency->code }}</span>
                <span class="text-muted ms-1">({{ $baseCurrency->name_ar }})</span>
                <span class="mx-2">—</span>
                <span class="fs-13 text-muted">يتم احتساب جميع أسعار الصرف كعدد وحدات من العملة الأساسية لكل وحدة واحدة من العملة المقابلة (1 Target = Rate × Base). المصدر الوحيد لتغيير العملة الأساسية هو صفحة الإعدادات العامة.</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0">{{ $editingRate ? 'تعديل سعر الصرف' : 'إضافة سعر صرف جديد' }}</h2>
            </div>
            <div class="card-body">
                @if(auth()->user()->hasPermission($editingRate ? 'admin.exchange_rates.update' : 'admin.exchange_rates.create'))
                    <form method="POST" action="{{ $editingRate ? route('admin.master-data.exchange-rates.update', $editingRate) : route('admin.master-data.exchange-rates.store') }}">
                        @csrf
                        @if($editingRate) @method('PUT') @endif

                        <div class="mb-3">
                            <label for="target_currency_id" class="form-label required">العملة المقابلة (Target Currency)</label>
                            <select class="form-control @error('target_currency_id') is-invalid @enderror @error('from_currency_id') is-invalid @enderror" data-choices id="target_currency_id" name="target_currency_id" required>
                                <option value="">اختر العملة</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" @selected(old('target_currency_id', $editingRate?->from_currency_id) == $currency->id)>
                                        {{ $currency->code }} — {{ $currency->name_ar }}
                                    </option>
                                @endforeach
                            </select>
                            @error('target_currency_id')<span class="field-error">{{ $message }}</span>@enderror
                            @error('from_currency_id')<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="rate" class="form-label required">سعر الصرف مقابل {{ $baseCurrency->code }}</label>
                            <div class="input-group" dir="ltr">
                                <span class="input-group-text">1 Target =</span>
                                <input type="number" class="form-control text-end @error('rate') is-invalid @enderror" id="rate" name="rate" value="{{ old('rate', $editingRate?->rate) }}" min="0.00000001" step="0.00000001" required placeholder="مثال: 140.00">
                                <span class="input-group-text">{{ $baseCurrency->code }}</span>
                            </div>
                            <div class="form-text fs-12 text-muted mt-1">
                                مثال: إذا كان 1 ريال سعودي = 140 {{ $baseCurrency->code }}، أدخل 140.
                            </div>
                            @error('rate')<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="effective_at" class="form-label required">تاريخ ووقت السريان</label>
                            <input type="text" class="form-control @error('effective_at') is-invalid @enderror" data-provider="flatpickr" data-date-format="Y-m-d H:i" data-enable-time="true" id="effective_at" name="effective_at" value="{{ old('effective_at', $editingRate?->effective_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')) }}" required placeholder="YYYY-MM-DD HH:MM">
                            @error('effective_at')<span class="field-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">
                                <i class="ri-save-line me-1"></i> {{ $editingRate ? 'حفظ التعديل' : 'إضافة السعر' }}
                            </button>
                            @if($editingRate)
                                <a class="btn btn-light" href="{{ route('admin.master-data.exchange-rates') }}">إلغاء</a>
                            @endif
                        </div>
                    </form>
                @else
                    <div class="alert alert-info mb-0">ليس لديك صلاحية إضافة أو تعديل أسعار الصرف.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0">السجل التاريخي لأسعار الصرف</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>العملة المقابلة</th>
                                <th>السعر مقابل العملة الأساسية ({{ $baseCurrency->code }})</th>
                                <th>تاريخ السريان</th>
                                <th class="text-end">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rates as $rate)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary fw-bold fs-12">{{ $rate->fromCurrency->code }}</span>
                                        <span class="text-muted fs-13 ms-1">{{ $rate->fromCurrency->name_ar }}</span>
                                    </td>
                                    <td dir="ltr" class="fw-bold">
                                        1 {{ $rate->fromCurrency->code }} = {{ number_format((float) $rate->rate, 4) }} {{ $rate->toCurrency->code }}
                                    </td>
                                    <td>{{ $rate->effective_at->format('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        @if(auth()->user()->hasPermission('admin.exchange_rates.update'))
                                            <a class="btn btn-sm btn-soft-primary" href="{{ route('admin.master-data.exchange-rates.edit', $rate) }}" aria-label="تعديل سعر الصرف">
                                                <i class="ri-pencil-line"></i> تعديل
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="text-center py-5">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                    <i class="ri-exchange-dollar-line"></i>
                                                </div>
                                            </div>
                                            <h5 class="fs-16 mb-1">لا توجد أسعار صرف مسجلة</h5>
                                            <p class="text-muted mb-0">أضف سعر صرف للعملات الأجنبية مقابل {{ $baseCurrency->code }}.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">{{ $rates->links() }}</div>
        </div>
    </div>
</div>
@endsection
