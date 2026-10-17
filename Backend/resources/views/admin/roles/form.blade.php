@extends('layouts.admin')
@php use App\Support\PermissionLabel; @endphp
@section('title', ($role ? 'تعديل دور' : 'دور جديد').' | لمّة')
@section('page-title', $role ? 'تعديل دور' : 'دور جديد')
@section('content')
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0">بيانات الدور</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ $role ? route('admin.roles.web.update',$role) : route('admin.roles.web.store') }}">
                    @csrf
                    @if($role) @method('PUT') @endif
                    <div class="mb-3">
                        <label for="name" class="form-label required">الاسم البرمجي</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name',$role?->name) }}" required dir="ltr" @disabled($role?->name === 'super_admin')>
                        @if($role?->name === 'super_admin')
                            <input type="hidden" name="name" value="super_admin">
                        @endif
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="display_name" class="form-label required">اسم العرض</label>
                        <input class="form-control @error('display_name') is-invalid @enderror" id="display_name" name="display_name" value="{{ old('display_name',$role?->display_name) }}" required>
                        @error('display_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">الوصف</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description',$role?->description) }}</textarea>
                        @error('description')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-actions">
                        <a class="btn btn-light" href="{{ route('admin.roles.web.index') }}">إلغاء</a>
                        <button class="btn btn-primary" type="submit"><i class="ri-save-line me-1"></i> حفظ الدور</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($role && auth()->user()->hasPermission('admin.roles.assign'))
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h2 class="card-title mb-0">صلاحيات الدور</h2>
                <span class="badge bg-primary-subtle text-primary fs-12">احفظ من زر «حفظ الصلاحيات» أدناه</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.roles.web.permissions',$role) }}" id="role-permissions-form">
                    @csrf
                    @method('PUT')
                    <div class="alert alert-light border fs-13 mb-3">
                        تغيير مربعات الصلاحيات لا يُحفظ مع زر «حفظ الدور» على اليسار — استخدم زر <strong>حفظ الصلاحيات</strong> في هذه البطاقة.
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="button" class="btn btn-soft-primary btn-sm" id="permissions-select-all">
                            <i class="ri-checkbox-multiple-line me-1"></i> اختيار الكل
                        </button>
                        <button type="button" class="btn btn-soft-secondary btn-sm" id="permissions-deselect-all">
                            <i class="ri-checkbox-blank-line me-1"></i> إلغاء الكل
                        </button>
                        <input type="search" id="permissions-search" class="form-control form-control-sm" style="max-width:220px" placeholder="بحث: محادثات الدعم، العملاء…">
                        <span class="text-muted fs-12 ms-auto" id="permissions-selected-count"></span>
                    </div>

                    <div class="permission-grid">
                        @foreach($permissionGroups as $group => $permissions)
                            <fieldset class="permission-group" data-permission-group="{{ $group }}" data-group-label="{{ PermissionLabel::group($group) }}">
                                <legend class="permission-group-legend">
                                    <span class="permission-group-title">{{ PermissionLabel::group($group) }}</span>
                                    <span class="permission-group-actions">
                                        <button type="button" class="btn btn-link btn-sm p-0 js-group-select-all">اختيار الكل</button>
                                        <span class="text-muted mx-1">|</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 js-group-deselect-all">إلغاء</button>
                                    </span>
                                </legend>
                                @foreach($permissions as $permission)
                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input permission-checkbox"
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->name }}"
                                            id="permission-{{ $permission->id }}"
                                            data-group="{{ $group }}"
                                            @checked(in_array($permission->name, old('permissions', $role->permissions->pluck('name')->all()), true))
                                        >
                                        <label class="form-check-label" for="permission-{{ $permission->id }}" data-permission-label="{{ PermissionLabel::for($permission->name) }} {{ $permission->name }}">
                                            {{ PermissionLabel::for($permission->name) }}
                                        </label>
                                    </div>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>

                    @error('permissions')<span class="field-error">{{ $message }}</span>@enderror

                    <div class="form-actions mt-4">
                        <button class="btn btn-primary" type="submit">
                            <i class="ri-key-2-line me-1"></i> حفظ الصلاحيات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@if($role && auth()->user()->hasPermission('admin.roles.assign'))
@push('scripts')
<script>
(function () {
    const form = document.getElementById('role-permissions-form');
    if (!form) return;

    const boxes = () => Array.from(form.querySelectorAll('.permission-checkbox'));
    const countEl = document.getElementById('permissions-selected-count');

    function updateCount() {
        const all = boxes();
        const selected = all.filter((box) => box.checked).length;
        if (countEl) {
            countEl.textContent = selected + ' / ' + all.length + ' محددة';
        }
    }

    function setAll(checked) {
        boxes().forEach((box) => { box.checked = checked; });
        updateCount();
    }

    function setGroup(fieldset, checked) {
        fieldset.querySelectorAll('.permission-checkbox').forEach((box) => {
            box.checked = checked;
        });
        updateCount();
    }

    document.getElementById('permissions-select-all')?.addEventListener('click', () => setAll(true));
    document.getElementById('permissions-deselect-all')?.addEventListener('click', () => setAll(false));

    form.querySelectorAll('[data-permission-group]').forEach((fieldset) => {
        fieldset.querySelector('.js-group-select-all')?.addEventListener('click', () => setGroup(fieldset, true));
        fieldset.querySelector('.js-group-deselect-all')?.addEventListener('click', () => setGroup(fieldset, false));
    });

    form.addEventListener('change', (event) => {
        if (event.target.classList.contains('permission-checkbox')) {
            updateCount();
        }
    });

    document.getElementById('permissions-search')?.addEventListener('input', (event) => {
        const query = String(event.target.value || '').trim().toLowerCase();
        form.querySelectorAll('[data-permission-group]').forEach((fieldset) => {
            const groupLabel = String(fieldset.dataset.groupLabel || '').toLowerCase();
            const groupKey = String(fieldset.dataset.permissionGroup || '').toLowerCase();
            let visibleCount = 0;
            fieldset.querySelectorAll('.form-check').forEach((row) => {
                const label = row.querySelector('[data-permission-label]');
                const haystack = String(label?.dataset.permissionLabel || label?.textContent || '').toLowerCase();
                const match = !query || haystack.includes(query) || groupLabel.includes(query) || groupKey.includes(query);
                row.classList.toggle('d-none', !match);
                if (match) visibleCount += 1;
            });
            fieldset.classList.toggle('d-none', query !== '' && visibleCount === 0);
        });
    });

    updateCount();
})();
</script>
@endpush
@endif
