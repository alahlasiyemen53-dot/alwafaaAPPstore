@extends('layouts.admin')
@section('title', 'قيم الخاصية: ' . $attribute->name_ar . ' | الوفاء استور')
@section('page-title', 'قيم الخاصية: ' . $attribute->name_ar)
@section('content')
<div class="page-heading">
    <div>
        <h2 class="fs-18 mb-1">قيم الخاصية: <span class="text-primary">{{ $attribute->name_ar }}</span></h2>
        <p class="text-muted mb-0">إدارة الخيارات المعيارية المتاحة لهذه الخاصية (الرمز: <code>{{ $attribute->code }}</code>)</p>
    </div>
    <div>
        <a class="btn btn-light" href="{{ route('admin.attributes.index') }}"><i class="ri-arrow-right-line me-1"></i> العودة للخصائص</a>
    </div>
</div>

<div class="row">
    <!-- Add New Value Form -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="ri-add-circle-line me-1 text-primary"></i> إضافة قيمة جديدة</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.attributes.values.store', $attribute) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="value_ar" class="form-label">القيمة بالعربي <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('value_ar') is-invalid @enderror" id="value_ar" name="value_ar" value="{{ old('value_ar') }}" required placeholder="مثال: أحمر أو 50ml">
                        @error('value_ar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="value_en" class="form-label">القيمة بالإنجليزي</label>
                        <input type="text" class="form-control @error('value_en') is-invalid @enderror" id="value_en" name="value_en" value="{{ old('value_en') }}" dir="ltr" placeholder="e.g. Red or 50ml">
                        @error('value_en')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label">الرمز البرمجي <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required dir="ltr" placeholder="e.g. red or 50ml">
                        <small class="text-muted">فريد ضمن هذه الخاصية</small>
                        @error('code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="sort_order" class="form-label">الترتيب</label>
                        <input type="number" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                            <label class="form-check-label fw-semibold" for="is_active">القيمة نشطة</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-add-line me-1"></i> إضافة القيمة
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Values List -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">القيم المسجلة ({{ $values->count() }})</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>القيمة بالعربي</th>
                                <th>القيمة بالإنجليزي</th>
                                <th>الرمز</th>
                                <th>الترتيب</th>
                                <th>الحالة</th>
                                <th class="text-end" style="width: 150px;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($values as $val)
                                <tr>
                                    <td>{{ $val->id }}</td>
                                    <td class="fw-semibold">{{ $val->value_ar }}</td>
                                    <td dir="ltr">{{ $val->value_en ?: '—' }}</td>
                                    <td><code>{{ $val->code }}</code></td>
                                    <td>{{ $val->sort_order }}</td>
                                    <td>
                                        @if($val->is_active)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">نشط</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">معطل</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-1">
                                            <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#editValueModal{{ $val->id }}" title="تعديل">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                            @if($val->is_active)
                                                <form method="POST" action="{{ route('admin.attributes.values.deactivate', [$attribute, $val]) }}" class="d-inline" data-confirm="هل تريد تعطيل القيمة '{{ $val->value_ar }}'؟">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger" title="تعطيل"><i class="ri-forbid-line"></i></button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.attributes.values.activate', [$attribute, $val]) }}" class="d-inline">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-soft-success" title="تفعيل"><i class="ri-check-line"></i></button>
                                                </form>
                                            @endif
                                        </div>

                                        <!-- Edit Value Modal -->
                                        <div class="modal fade text-start" id="editValueModal{{ $val->id }}" tabindex="-1" aria-labelledby="editValueModalLabel{{ $val->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('admin.attributes.values.update', [$attribute, $val]) }}">
                                                        @csrf @method('PUT')
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="editValueModalLabel{{ $val->id }}">تعديل القيمة: {{ $val->value_ar }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">القيمة بالعربي <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="value_ar" value="{{ $val->value_ar }}" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">القيمة بالإنجليزي</label>
                                                                <input type="text" class="form-control" name="value_en" value="{{ $val->value_en }}" dir="ltr">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">الرمز البرمجي <span class="text-danger">*</span></label>
                                                                <input type="text" class="form-control" name="code" value="{{ $val->code }}" required dir="ltr">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">الترتيب</label>
                                                                <input type="number" class="form-control" name="sort_order" value="{{ $val->sort_order }}" min="0">
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="is_active_{{ $val->id }}" name="is_active" value="1" {{ $val->is_active ? 'checked' : '' }}>
                                                                <label class="form-check-label fw-semibold" for="is_active_{{ $val->id }}">القيمة نشطة</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                                                            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <div class="avatar-md mx-auto mb-3">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-24">
                                                <i class="ri-list-check"></i>
                                            </div>
                                        </div>
                                        <h5 class="fs-14 mb-1">لا توجد قيم مسجلة</h5>
                                        <p class="text-muted mb-0">لا توجد أي قيم مضافة لهذه الخاصية بعد. استخدم النموذج لإضافة أول قيمة.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
