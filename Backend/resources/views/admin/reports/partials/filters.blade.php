@php
    $preset = request('preset', 'this_month');
@endphp
<form method="GET" class="row g-2 align-items-end filter-row mb-4">
    <div class="col-md-3">
        <label class="form-label fs-12 text-muted mb-1">الفترة الزمنية</label>
        <select name="preset" class="form-select" onchange="this.form.submit()">
            <option value="today" @selected($preset === 'today')>اليوم</option>
            <option value="yesterday" @selected($preset === 'yesterday')>الأمس</option>
            <option value="this_week" @selected($preset === 'this_week')>هذا الأسبوع</option>
            <option value="this_month" @selected($preset === 'this_month')>هذا الشهر</option>
            <option value="custom" @selected($preset === 'custom')>نطاق مخصص</option>
        </select>
    </div>
    @if($preset === 'custom')
        <div class="col-md-2">
            <label for="filter_date_from" class="form-label fs-12 text-muted mb-1">من تاريخ</label>
            <input type="text" name="from" id="filter_date_from" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('from')) }}" placeholder="يوم-شهر-سنة">
        </div>
        <div class="col-md-2">
            <label for="filter_date_to" class="form-label fs-12 text-muted mb-1">إلى تاريخ</label>
            <input type="text" name="to" id="filter_date_to" class="form-control" data-provider="flatpickr" data-date-format="d-m-Y" value="{{ \App\Support\DateFilterNormalizer::formatDisplay(request('to')) }}" placeholder="يوم-شهر-سنة">
        </div>
    @endif
    @if(isset($currencies))
        <div class="col-md-2">
            <label class="form-label fs-12 text-muted mb-1">العملة</label>
            <select name="currency_id" class="form-select">
                <option value="">جميع العملات</option>
                @foreach($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((string) request('currency_id') === (string) $currency->id)>{{ $currency->code }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-secondary"><i class="ri-filter-2-line me-1"></i> تطبيق</button>
        <a href="{{ url()->current() }}" class="btn btn-light"><i class="ri-refresh-line"></i></a>
    </div>
</form>
<div class="text-muted fs-13 mb-3">
    <i class="ri-calendar-line me-1"></i>
    من {{ $filters->range->startAt->format('d-m-Y') }} إلى {{ $filters->range->endAt->subDay()->format('d-m-Y') }}
</div>
