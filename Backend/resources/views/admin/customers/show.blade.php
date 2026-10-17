@extends('layouts.admin')
@section('title', 'تفاصيل العميل | ' . $customer->name)
@section('page-title', 'تفاصيل العميل')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .leaflet-container {
        font-family: inherit;
    }
    .address-card-hover {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .address-card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }
</style>
@endpush

@section('content')
<div class="row">
    <!-- Customer Info & Phone Verification -->
    <div class="col-xl-4">
        <!-- Customer Profile Card -->
        <div class="card">
            <div class="card-body text-center">
                <div class="avatar-lg mx-auto mb-3">
                    <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-24 fw-bold">
                        {{ mb_substr($customer->name, 0, 1) }}
                    </span>
                </div>
                <h5 class="mb-1">{{ $customer->name }}</h5>
                <p class="text-muted mb-3" dir="ltr">{{ $customer->phone }}</p>

                <div class="d-flex justify-content-center gap-2 mb-3">
                    @if($customer->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-12">
                            <i class="ri-checkbox-circle-line me-1"></i> حساب نشط
                        </span>
                    @else
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-12">
                            <i class="ri-close-circle-line me-1"></i> حساب معطل
                        </span>
                    @endif

                    @if($customer->isPhoneVerified())
                        <span class="badge {{ $customer->phone_verification_method?->badgeClass() ?? 'bg-success-subtle text-success' }} px-3 py-2 fs-12">
                            <i class="{{ $customer->phone_verification_method?->icon() ?? 'ri-shield-check-line' }} me-1"></i>
                            الهاتف موثق
                        </span>
                    @else
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fs-12">
                            <i class="ri-shield-flash-line me-1"></i> هاتف غير موثق
                        </span>
                    @endif
                </div>
                <!-- Customer Tier Badge & Action (Sprint 21.8) -->
                <div class="p-3 bg-light rounded text-start mb-3 border">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fs-12 text-muted fw-medium">مستوى العميل:</span>
                        @if($customer->tier)
                            <span class="badge {{ $customer->tier->is_default ? 'bg-secondary-subtle text-secondary border border-secondary-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle' }} fs-12 px-2 py-1">
                                <i class="{{ $customer->tier->is_default ? 'ri-shield-user-line' : 'ri-vip-crown-line' }} me-1"></i>
                                {{ $customer->tier->name_ar }}
                            </span>
                        @else
                            <span class="badge bg-light text-muted border">غير محدد</span>
                        @endif
                    </div>
                    @if($customer->currentTierMembership)
                        <div class="fs-11 text-muted mb-2">
                            <div><i class="ri-calendar-line me-1"></i> عضو منذ: {{ $customer->currentTierMembership->starts_at->format('Y-m-d') }}</div>
                            <div><i class="ri-information-line me-1"></i> المصدر: {{ $customer->currentTierMembership->assignment_source->label() }}</div>
                        </div>
                    @endif
                    @if(auth()->user()->hasPermission('admin.customer_tiers.assign'))
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#changeTierModal">
                            <i class="ri-swap-line me-1"></i> تغيير مستوى العميل
                        </button>
                    @endif
                </div>

                @if(auth()->user()->hasPermission('admin.customers.update'))
                    <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-sm btn-subtle-primary w-100 mb-2">
                        <i class="ri-pencil-line me-1"></i> تعديل بيانات العميل
                    </a>
                @endif
            </div>
            <div class="card-body border-top">
                <h6 class="fs-14 mb-3">البيانات الشخصية</h6>
                <ul class="list-unstyled mb-0 vstack gap-3">
                    <li>
                        <div class="d-flex align-items-center">
                            <i class="ri-phone-line text-muted me-2 fs-16"></i>
                            <div class="flex-grow-1">
                                <span class="text-muted fs-12 d-block">الهاتف الكنسي / المعياري</span>
                                <span class="fw-semibold fs-13" dir="ltr">{{ $customer->normalized_phone }}</span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="d-flex align-items-center">
                            <i class="ri-mail-line text-muted me-2 fs-16"></i>
                            <div class="flex-grow-1">
                                <span class="text-muted fs-12 d-block">البريد الإلكتروني</span>
                                <span class="fw-semibold fs-13" dir="ltr">{{ $customer->email ?: 'غير محدد' }}</span>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="d-flex align-items-center">
                            <i class="ri-calendar-line text-muted me-2 fs-16"></i>
                            <div class="flex-grow-1">
                                <span class="text-muted fs-12 d-block">تاريخ التسجيل</span>
                                <span class="fw-semibold fs-13">{{ $customer->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    </li>
                    @if($customer->notes)
                        <li>
                            <div class="d-flex align-items-start">
                                <i class="ri-file-text-line text-muted me-2 fs-16 mt-1"></i>
                                <div class="flex-grow-1">
                                    <span class="text-muted fs-12 d-block">ملاحظات</span>
                                    <span class="fs-13 text-dark">{{ $customer->notes }}</span>
                                </div>
                            </div>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        <!-- Phone Verification Management Card -->
        <div class="card">
            <div class="card-header bg-light-subtle">
                <h5 class="card-title mb-0">
                    <i class="ri-shield-keyhole-line me-1 text-primary"></i> توثيق رقم الهاتف
                </h5>
            </div>
            <div class="card-body">
                @if($customer->isPhoneVerified())
                    <div class="p-3 bg-success-subtle rounded border border-success-subtle mb-3">
                        <div class="d-flex align-items-center">
                            <i class="ri-checkbox-circle-fill text-success fs-24 me-2"></i>
                            <div>
                                <h6 class="text-success mb-1">رقم الهاتف موثق بنجاح</h6>
                                <p class="text-muted fs-12 mb-0">الرقم الموثق: <strong dir="ltr">{{ $customer->normalized_phone }}</strong></p>
                            </div>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless fs-13 mb-0">
                        <tr>
                            <td class="text-muted py-1" style="width: 120px;">طريقة التوثيق:</td>
                            <td class="fw-semibold py-1">
                                <span class="badge {{ $customer->phone_verification_method?->badgeClass() }}">
                                    {{ $customer->phone_verification_method?->label() }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted py-1">تاريخ التوثيق:</td>
                            <td class="fw-semibold py-1">{{ $customer->phone_verified_at?->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        @if($customer->phone_verified_by && $customer->phoneVerifier)
                            <tr>
                                <td class="text-muted py-1">المشرف الموثق:</td>
                                <td class="fw-semibold py-1">
                                    <i class="ri-user-star-line text-primary me-1"></i>
                                    {{ $customer->phoneVerifier->name }}
                                </td>
                            </tr>
                        @endif
                    </table>
                @else
                    <div class="p-3 bg-warning-subtle rounded border border-warning-subtle mb-3">
                        <div class="d-flex align-items-center">
                            <i class="ri-error-warning-fill text-warning fs-24 me-2"></i>
                            <div>
                                <h6 class="text-warning mb-1">الهاتف غير موثق</h6>
                                <p class="text-muted fs-12 mb-0">لم يتم تأكيد ملكية رقم الهاتف الحالي لهذا العميل.</p>
                            </div>
                        </div>
                    </div>

                    @if(auth()->user()->hasPermission('admin.customers.verify_phone'))
                        <form id="verify-phone-form" action="{{ route('admin.customers.verify-phone', $customer) }}" method="POST">
                            @csrf
                            <button type="button" id="btn-manual-verify" class="btn btn-primary w-100">
                                <i class="ri-shield-check-line me-1"></i> تأكيد رقم الهاتف يدويًا
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Customer Addresses Section -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="card-title mb-0"><i class="ri-map-pin-2-line me-1 text-primary"></i> العناوين المحفوظة ({{ $customer->addresses->count() }})</h4>
                    @if(auth()->user()->hasPermission('admin.customer_addresses.view') || auth()->user()->hasPermission('admin.customer_addresses.create') || auth()->user()->hasPermission('admin.customer_addresses.update'))
                        <p class="text-muted fs-12 mb-0">إدارة عناوين التوصيل للعميل وتحديد العنوان الافتراضي.</p>
                    @endif
                </div>
                @if(auth()->user()->hasPermission('admin.customer_addresses.create'))
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#createAddressModal">
                        <i class="ri-add-line me-1"></i> إضافة عنوان جديد
                    </button>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @forelse($customer->addresses as $address)
                        <div class="col-md-6">
                            <div class="card border mb-0 h-100 address-card-hover {{ $address->is_default ? 'border-primary shadow-sm' : 'border-light-subtle' }}">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-16" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;">
                                                @if(str_contains($address->label, 'عمل') || str_contains($address->label, 'مكتب'))
                                                    <i class="ri-briefcase-line"></i>
                                                @elseif(str_contains($address->label, 'شقة'))
                                                    <i class="ri-building-line"></i>
                                                @elseif(str_contains($address->label, 'منزل') || str_contains($address->label, 'بيت'))
                                                    <i class="ri-home-4-line"></i>
                                                @else
                                                    <i class="ri-map-pin-user-line"></i>
                                                @endif
                                            </span>
                                            <div>
                                                <h5 class="fs-15 mb-0 fw-bold text-dark">{{ $address->label }}</h5>
                                                <span class="text-muted fs-11">#{{ $address->id }}</span>
                                            </div>
                                        </div>
                                        @if($address->is_default)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-12 px-2 py-1">
                                                <i class="ri-star-fill text-warning me-1"></i> العنوان الافتراضي
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Recipient Details: اسم المستلم وتلفون المستلم -->
                                    <div class="bg-light-subtle p-2 rounded border mb-2">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-sm-6">
                                                <span class="text-muted fs-11 d-block"><i class="ri-user-3-line me-1 text-primary"></i> المستلم:</span>
                                                <span class="fs-13 fw-semibold text-dark">{{ $address->contact_name ?: ($customer->name ?? 'غير محدد') }}</span>
                                            </div>
                                            <div class="col-sm-6">
                                                <span class="text-muted fs-11 d-block"><i class="ri-phone-line me-1 text-success"></i> الهاتف:</span>
                                                @if($address->contact_phone || $customer->phone)
                                                    <a href="tel:{{ $address->contact_phone ?: $customer->phone }}" class="fs-13 fw-semibold text-success text-decoration-none" dir="ltr">
                                                        {{ $address->contact_phone ?: $customer->phone }}
                                                    </a>
                                                @else
                                                    <span class="text-muted fs-12">غير محدد</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Geography: الدولة والمحافظة والمدينة والمديرية والحي -->
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-11">
                                            <i class="ri-earth-line me-1"></i> {{ $address->country?->name_ar ?? 'الدولة غير محددة' }}
                                        </span>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-11">
                                            <i class="ri-building-2-line me-1"></i> {{ $address->governorate?->name_ar ?? 'المحافظة غير محددة' }}
                                        </span>
                                        @if($address->city)
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-11">
                                                <i class="ri-community-line me-1"></i> {{ $address->city->name_ar }}
                                            </span>
                                        @endif
                                        @if($address->district)
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-11">
                                                <i class="ri-road-map-line me-1"></i> {{ $address->district->name_ar }}
                                            </span>
                                        @endif
                                        @if($address->neighborhood)
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-11">
                                                <i class="ri-map-pin-line me-1"></i> {{ $address->neighborhood }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Address text & building info -->
                                    <div class="mb-2">
                                        <p class="text-dark fs-13 mb-1">
                                            <i class="ri-map-pin-2-line text-muted me-1"></i>
                                            {{ $address->formatted_address ?: $address->fullAddressText() ?: 'لا توجد تفاصيل نصية' }}
                                        </p>
                                        @if($address->street || $address->building || $address->floor || $address->apartment || $address->landmark)
                                            <div class="text-muted fs-11 ps-2">
                                                @if($address->street)<span class="me-2"><i class="ri-road-map-line"></i> الشارع: {{ $address->street }}</span>@endif
                                                @if($address->building)<span class="me-2">مبنى: {{ $address->building }}</span>@endif
                                                @if($address->floor)<span class="me-2">طابق: {{ $address->floor }}</span>@endif
                                                @if($address->apartment)<span class="me-2">شقة: {{ $address->apartment }}</span>@endif
                                                @if($address->landmark)<span class="me-2 text-info"><i class="ri-flag-line"></i> معلم: {{ $address->landmark }}</span>@endif
                                            </div>
                                        @endif
                                    </div>

                                    @if($address->notes)
                                        <p class="text-muted fs-12 mb-2 bg-light p-2 rounded border-start border-3 border-info">
                                            <i class="ri-sticky-note-line me-1 text-info"></i> {{ $address->notes }}
                                        </p>
                                    @endif

                                    <!-- Map coordinates & action -->
                                    <div class="mt-auto pt-2">
                                        @if($address->latitude !== null && $address->longitude !== null)
                                            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border mb-2">
                                                <div class="fs-11 text-muted" dir="ltr">
                                                    <i class="ri-map-pin-range-fill text-danger me-1"></i> {{ number_format($address->latitude, 6) }}, {{ number_format($address->longitude, 6) }}
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-xs btn-subtle-primary view-address-map-btn"
                                                        data-latitude="{{ $address->latitude }}"
                                                        data-longitude="{{ $address->longitude }}"
                                                        data-label="{{ $address->label }}"
                                                        data-address="{{ $address->formatted_address ?: $address->fullAddressText() }}">
                                                        <i class="ri-map-2-line me-1"></i> عرض الخريطة
                                                    </button>
                                                    <a href="https://www.google.com/maps?q={{ $address->latitude }},{{ $address->longitude }}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-secondary" title="فتح في خرائط Google">
                                                        <i class="ri-external-link-line"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <div class="fs-11 text-warning bg-warning-subtle p-2 rounded mb-2 d-flex align-items-center">
                                                <i class="ri-alert-line me-1"></i> لم يتم تحديد الإحداثيات على الخريطة
                                            </div>
                                        @endif

                                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                                            <div>
                                                @if(! $address->is_default && auth()->user()->hasPermission('admin.customer_addresses.set_default'))
                                                    <form action="{{ route('admin.customers.addresses.set-default', [$customer, $address]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm btn-subtle-secondary" title="تعيين كافتراضي">
                                                            <i class="ri-star-line me-1"></i> تعيين كافتراضي
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                            <div class="d-flex gap-1">
                                                @if(auth()->user()->hasPermission('admin.customer_addresses.update'))
                                                    <button type="button" class="btn btn-sm btn-subtle-info edit-address-btn" 
                                                        data-id="{{ $address->id }}"
                                                        data-label="{{ $address->label }}"
                                                        data-contact_name="{{ $address->contact_name }}"
                                                        data-contact_phone="{{ $address->contact_phone }}"
                                                        data-country_id="{{ $address->country_id }}"
                                                        data-governorate_id="{{ $address->governorate_id }}"
                                                        data-city_id="{{ $address->city_id }}"
                                                        data-district_id="{{ $address->district_id }}"
                                                        data-formatted_address="{{ $address->formatted_address }}"
                                                        data-neighborhood="{{ $address->neighborhood }}"
                                                        data-street="{{ $address->street }}"
                                                        data-building="{{ $address->building }}"
                                                        data-floor="{{ $address->floor }}"
                                                        data-apartment="{{ $address->apartment }}"
                                                        data-landmark="{{ $address->landmark }}"
                                                        data-notes="{{ $address->notes }}"
                                                        data-latitude="{{ $address->latitude }}"
                                                        data-longitude="{{ $address->longitude }}"
                                                        data-is_default="{{ $address->is_default ? '1' : '0' }}"
                                                        data-action="{{ route('admin.customers.addresses.update', [$customer, $address]) }}"
                                                        title="تعديل العنوان">
                                                        <i class="ri-pencil-line me-1"></i> تعديل
                                                    </button>
                                                @endif
                                                @if(auth()->user()->hasPermission('admin.customer_addresses.delete'))
                                                    <form action="{{ route('admin.customers.addresses.destroy', [$customer, $address]) }}" method="POST" class="d-inline delete-address-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="btn btn-sm btn-subtle-danger delete-address-btn" title="حذف العنوان">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-5">
                            <div class="avatar-lg mx-auto mb-3">
                                <span class="avatar-title bg-light text-muted rounded-circle fs-2">
                                    <i class="ri-map-pin-line"></i>
                                </span>
                            </div>
                            <h5 class="text-muted">لا توجد عناوين مسجلة</h5>
                            <p class="text-muted mb-3">لم يتم تسجيل أي عناوين توصيل لهذا العميل حتى الآن.</p>
                            @if(auth()->user()->hasPermission('admin.customer_addresses.create'))
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createAddressModal">
                                    <i class="ri-add-line me-1"></i> إضافة أول عنوان
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Customer Wishlist Section (Sprint 21.6) -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="ri-heart-3-line text-danger me-1"></i> قائمة الرغبات / المفضلة
                    <span class="badge bg-danger-subtle text-danger ms-2 fs-12">{{ number_format($wishlistCount) }}</span>
                </h5>
                @if($wishlistCount > 0 && auth()->user()->hasPermission('admin.wishlists.view'))
                    <a href="{{ route('admin.wishlists.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-subtle-primary">
                        <i class="ri-external-link-line me-1"></i> عرض السجل الكامل للمفضلة
                    </a>
                @endif
            </div>
            <div class="card-body">
                @if($wishlistItems->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">المنتج</th>
                                    <th scope="col">التصنيف / الماركة</th>
                                    <th scope="col">حالة المنتج</th>
                                    <th scope="col">تاريخ الإضافة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wishlistItems as $item)
                                    <tr>
                                        <td>
                                            @if($item->product)
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0 me-2">
                                                        @if($item->product->getPrimaryImageUrl('thumb'))
                                                            <img src="{{ $item->product->getPrimaryImageUrl('thumb') }}" alt="{{ $item->product->name_ar }}" class="rounded avatar-xs object-fit-cover">
                                                        @else
                                                            <div class="avatar-xs rounded bg-light text-muted d-flex align-items-center justify-content-center">
                                                                <i class="ri-image-line fs-14"></i>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <h6 class="fs-13 mb-0">
                                                            @if(auth()->user()->hasPermission('admin.products.view'))
                                                                <a href="{{ route('admin.products.show', $item->product) }}" class="text-dark">
                                                                    {{ $item->product->name_ar }}
                                                                </a>
                                                            @else
                                                                {{ $item->product->name_ar }}
                                                            @endif
                                                        </h6>
                                                        @if($item->product->name_en)
                                                            <small class="text-muted">{{ $item->product->name_en }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-muted">منتج محذوف</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-13 text-muted">
                                                {{ $item->product?->category?->name_ar ?? '—' }}
                                                @if($item->product?->brand)
                                                    / {{ $item->product->brand->name_ar }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            @if($item->product)
                                                @php($pStatus = is_object($item->product->status) ? $item->product->status->value : $item->product->status)
                                                @if($pStatus === 'active')
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">نشط</span>
                                                @elseif($pStatus === 'draft')
                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">مسودة</span>
                                                @elseif($pStatus === 'inactive')
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">معطل</span>
                                                @else
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">{{ $pStatus }}</span>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-muted">غير متوفر</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-13 text-muted">{{ $item->created_at->format('Y-m-d H:i') }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="avatar-md mx-auto mb-2">
                            <span class="avatar-title bg-light text-muted rounded-circle fs-3">
                                <i class="ri-heart-line"></i>
                            </span>
                        </div>
                        <h6 class="text-muted mb-1">المفضلة فارغة</h6>
                        <p class="text-muted fs-13 mb-0">لم يقم هذا العميل بإضافة أي منتجات إلى قائمة رغباته حتى الآن.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Customer Product Reviews Card -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="ri-star-line text-warning me-1"></i> مراجعات وتقييمات العميل
                    <span class="badge bg-warning-subtle text-warning ms-2 fs-12">{{ number_format($reviewsCount) }}</span>
                </h5>
                @if($reviewsCount > 0 && auth()->user()->hasPermission('admin.reviews.view'))
                    <a href="{{ route('admin.reviews.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-subtle-primary">
                        <i class="ri-external-link-line me-1"></i> عرض كافة المراجعات
                    </a>
                @endif
            </div>
            <div class="card-body">
                @if($recentReviews->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">المنتج</th>
                                    <th scope="col">التقييم</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">شراء مؤكد</th>
                                    <th scope="col">التاريخ</th>
                                    <th scope="col" class="text-end">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentReviews as $rev)
                                    <tr>
                                        <td>
                                            @if($rev->product)
                                                <a href="{{ route('admin.products.show', $rev->product_id) }}" class="text-dark fw-medium">
                                                    {{ $rev->product->name_ar }}
                                                </a>
                                            @else
                                                <span class="text-muted">منتج محذوف</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-warning-subtle text-warning fs-12">
                                                <i class="ri-star-fill me-1"></i> {{ $rev->rating }} / 5
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $rev->status->badgeClass() }}">
                                                {{ $rev->status->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($rev->is_verified_purchase)
                                                <span class="badge bg-info-subtle text-info">نعم</span>
                                            @else
                                                <span class="badge bg-light text-muted">لا</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-13 text-muted">{{ $rev->created_at->format('Y-m-d H:i') }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if(auth()->user()->hasPermission('admin.reviews.view'))
                                                <a href="{{ route('admin.reviews.show', $rev) }}" class="btn btn-sm btn-soft-primary" title="عرض وتعديل الحالة">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="avatar-md mx-auto mb-2">
                            <span class="avatar-title bg-light text-muted rounded-circle fs-3">
                                <i class="ri-star-line"></i>
                            </span>
                        </div>
                        <h6 class="text-muted mb-1">لا توجد مراجعات</h6>
                        <p class="text-muted fs-13 mb-0">لم يقم هذا العميل بكتابة أي مراجعات أو تقييمات للمنتجات بعد.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Customer Tier History Section (Sprint 21.8) -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="ri-history-line text-primary me-1"></i> سجل تصنيفات وعضويات العميل (VIP History)
                    <span class="badge bg-primary-subtle text-primary ms-2 fs-12">{{ number_format($tierHistoryCount) }}</span>
                </h5>
                @if(auth()->user()->hasPermission('admin.customer_tiers.assign'))
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#changeTierModal">
                        <i class="ri-swap-line me-1"></i> تغيير المستوى
                    </button>
                @endif
            </div>
            <div class="card-body">
                @if($tierHistory->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">المستوى</th>
                                    <th scope="col">من تاريخ</th>
                                    <th scope="col">إلى تاريخ</th>
                                    <th scope="col">الحالة</th>
                                    <th scope="col">المصدر</th>
                                    <th scope="col">المسؤول</th>
                                    <th scope="col">سبب التعيين / التغيير</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tierHistory as $history)
                                    <tr>
                                        <td>
                                            @if($history->tier)
                                                <span class="badge {{ $history->tier->is_default ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }} fs-12">
                                                    <i class="{{ $history->tier->is_default ? 'ri-shield-user-line' : 'ri-vip-crown-line' }} me-1"></i>
                                                    {{ $history->tier->name_ar }}
                                                </span>
                                            @else
                                                <span class="text-muted">مستوى محذوف</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-13">{{ $history->starts_at->format('Y-m-d H:i') }}</span>
                                        </td>
                                        <td>
                                            @if($history->ends_at)
                                                <span class="fs-13 text-muted">{{ $history->ends_at->format('Y-m-d H:i') }}</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">العضوية الحالية</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($history->isCurrent())
                                                <span class="badge bg-success-subtle text-success">فعال حالياً</span>
                                            @else
                                                <span class="badge bg-light text-muted">منتهي</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $history->assignment_source->badgeClass() }}">
                                                {{ $history->assignment_source->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($history->assignedBy)
                                                <span class="fs-13 text-dark fw-medium">{{ $history->assignedBy->name }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fs-13 text-muted" title="{{ $history->change_reason }}">{{ \Illuminate\Support\Str::limit($history->change_reason ?? '—', 45) }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="avatar-md mx-auto mb-2">
                            <span class="avatar-title bg-light text-muted rounded-circle fs-3">
                                <i class="ri-vip-crown-line"></i>
                            </span>
                        </div>
                        <h6 class="text-muted mb-1">لا يوجد سجل عضويات</h6>
                        <p class="text-muted fs-13 mb-0">لم يتم تسجيل أي تغييرات في مستوى هذا العميل حتى الآن.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal: Change Customer Tier (Sprint 21.8) -->
@if(auth()->user()->hasPermission('admin.customer_tiers.assign'))
<div class="modal fade" id="changeTierModal" tabindex="-1" aria-labelledby="changeTierModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTierModalLabel"><i class="ri-vip-crown-line me-1 text-primary"></i> تغيير مستوى العميل الإداري</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form action="{{ route('admin.customers.tier.assign', $customer) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning border-0 d-flex align-items-center mb-3">
                        <i class="ri-alert-line fs-20 me-2"></i>
                        <div class="fs-12">
                            <strong>تنبيه إداري:</strong> تغيير المستوى يمثل تصنيفاً ومستوى عضوية فقط، <strong>ولا يؤثر تلقائياً</strong> على المحفظة المالية أو نقاط الولاء أو الحسابات.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted fs-12 d-block">المستوى الحالي للعميل:</label>
                        <span class="badge {{ $customer->tier?->is_default ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }} fs-13 px-3 py-2">
                            <i class="{{ $customer->tier?->is_default ? 'ri-shield-user-line' : 'ri-vip-crown-line' }} me-1"></i>
                            {{ $customer->tier?->name_ar ?? 'غير محدد' }}
                        </span>
                    </div>

                    <div class="mb-3">
                        <label for="modal_tier_id" class="form-label required">المستوى الجديد المستهدف <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_tier_id" name="tier_id" required>
                            <option value="">-- اختر المستوى --</option>
                            @foreach($activeTiers as $t)
                                <option value="{{ $t->id }}" {{ $customer->current_tier_id === $t->id ? 'disabled' : '' }}>
                                    {{ $t->name_ar }} ({{ $t->code }}) {{ $customer->current_tier_id === $t->id ? '- المستوى الحالي' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_tier_reason" class="form-label required">سبب تغيير المستوى <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="modal_tier_reason" name="reason" rows="3" placeholder="اكتب مبرر تغيير المستوى (5 أحرف على الأقل)..." required minlength="5" maxlength="500"></textarea>
                        <div class="form-text">هذا الحقل إلزامي لتوثيق وتدقيق العمليات الإدارية في سجل التدقيق (Audit Log).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line me-1"></i> تأكيد تغيير المستوى
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="addressMapModal" tabindex="-1" aria-labelledby="addressMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title mb-0" id="addressMapModalLabel">
                        <i class="ri-map-2-line text-primary me-1"></i> موقع عنوان العميل على الخريطة
                    </h5>
                    <span class="fs-12 text-muted" id="addressMapModalSub"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body p-0">
                <div id="customer-address-map" style="height: 420px; width: 100%;"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <span class="fs-12 text-muted" id="addressMapCoords" dir="ltr"></span>
                <div>
                    <a href="#" target="_blank" rel="noopener" id="addressMapGoogleLink" class="btn btn-sm btn-outline-primary">
                        <i class="ri-external-link-line me-1"></i> فتح في خرائط Google
                    </a>
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create Customer Address -->
<div class="modal fade" id="createAddressModal" tabindex="-1" aria-labelledby="createAddressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form action="{{ route('admin.customers.addresses.store', $customer) }}" method="POST" id="create-address-form" class="modal-content">
            @csrf
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="createAddressModalLabel">
                    <i class="ri-map-pin-add-line me-1 text-primary"></i> إضافة عنوان توصيل جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <!-- القسم 1: التسمية وبيانات المستلم -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-user-3-line text-primary me-1"></i> بيانات التسمية والمستلم</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="create_label" class="form-label required">اسم العنوان / التسمية <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="create_label" name="label" placeholder="مثال: المنزل، العمل، الشقة الرئيسية..." required>
                                <div class="d-flex gap-1 mt-2 flex-wrap">
                                    <span class="text-muted fs-11 me-1 align-self-center">اقتراحات سريعة:</span>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="create_label" data-value="المنزل">🏠 المنزل</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="create_label" data-value="العمل">🏢 العمل</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="create_label" data-value="شقة">🚪 شقة</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="create_label" data-value="أخرى">📍 أخرى</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="create_contact_name" class="form-label">اسم المستلم</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-user-line"></i></span>
                                    <input type="text" class="form-control" id="create_contact_name" name="contact_name" value="{{ $customer->name }}" placeholder="اسم مستلم الطلب">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="create_contact_phone" class="form-label">هاتف المستلم</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                    <input type="text" class="form-control" id="create_contact_phone" name="contact_phone" value="{{ $customer->phone }}" placeholder="77xxxxxxx" dir="ltr">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 2: النطاق الجغرافي وتفاصيل العنوان -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-global-line text-info me-1"></i> النطاق الجغرافي وتفاصيل العنوان</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-lg-3">
                                <label for="create_country_id" class="form-label required">الدولة <span class="text-danger">*</span></label>
                                <select class="form-select" id="create_country_id" name="country_id" required>
                                    <option value="">اختر الدولة</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ $loop->first ? 'selected' : '' }}>{{ $country->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="create_governorate_id" class="form-label required">المحافظة <span class="text-danger">*</span></label>
                                <select class="form-select" id="create_governorate_id" name="governorate_id" required>
                                    <option value="">اختر المحافظة</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="create_city_id" class="form-label">المدينة</label>
                                <select class="form-select" id="create_city_id" name="city_id">
                                    <option value="">اختر المدينة (اختياري)</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="create_district_id" class="form-label">المديرية</label>
                                <select class="form-select" id="create_district_id" name="district_id">
                                    <option value="">اختر المديرية (اختياري)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="create_formatted_address" class="form-label">العنوان بالتفصيل / وصف المكان</label>
                                <input type="text" class="form-control" id="create_formatted_address" name="formatted_address" placeholder="مثال: صنعاء، شارع حدة، بجوار مركز الكميم">
                            </div>
                            <div class="col-md-4">
                                <label for="create_neighborhood" class="form-label">الحي / المنطقة</label>
                                <input type="text" class="form-control" id="create_neighborhood" name="neighborhood" placeholder="اسم الحي">
                            </div>
                            <div class="col-md-4">
                                <label for="create_street" class="form-label">الشارع</label>
                                <input type="text" class="form-control" id="create_street" name="street" placeholder="اسم الشارع">
                            </div>
                            <div class="col-md-4">
                                <label for="create_landmark" class="form-label">أقرب معلم بارز</label>
                                <input type="text" class="form-control" id="create_landmark" name="landmark" placeholder="بجوار مسجد / مدرسة...">
                            </div>
                            <div class="col-md-4">
                                <label for="create_building" class="form-label">رقم / اسم المبنى</label>
                                <input type="text" class="form-control" id="create_building" name="building" placeholder="رقم أو اسم العمارة">
                            </div>
                            <div class="col-md-4">
                                <label for="create_floor" class="form-label">الطابق</label>
                                <input type="text" class="form-control" id="create_floor" name="floor" placeholder="رقم الطابق">
                            </div>
                            <div class="col-md-4">
                                <label for="create_apartment" class="form-label">رقم الشقة</label>
                                <input type="text" class="form-control" id="create_apartment" name="apartment" placeholder="رقم الشقة">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 3: تحديد الموقع بالخريطة -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-map-pin-range-line text-danger me-1"></i> تحديد الموقع على الخريطة (GPS)</span>
                        <button type="button" class="btn btn-xs btn-outline-primary" id="btn-create-locate-me">
                            <i class="ri-crosshair-2-line me-1"></i> موقعي الحالي
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-subtle-info fs-12 py-2 mb-2">
                            <i class="ri-information-line me-1"></i> انقر على الخريطة أو اسحب العلامة لتحديد موقع التوصيل بدقة وتحديث الإحداثيات تلقائياً.
                        </div>
                        <div id="create-address-picker-map" style="height: 240px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 12px; z-index: 1;"></div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="create_latitude" class="form-label fs-12">خط العرض (Latitude)</label>
                                <input type="number" step="any" class="form-control form-control-sm" id="create_latitude" name="latitude" placeholder="15.369445" dir="ltr">
                            </div>
                            <div class="col-md-6">
                                <label for="create_longitude" class="form-label fs-12">خط الطول (Longitude)</label>
                                <input type="number" step="any" class="form-control form-control-sm" id="create_longitude" name="longitude" placeholder="44.191006" dir="ltr">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 4: ملاحظات وخيارات -->
                <div class="card border mb-0 shadow-none">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="create_notes" class="form-label">ملاحظات العنوان أو تعليمات التوصيل</label>
                            <textarea class="form-control" id="create_notes" name="notes" rows="2" placeholder="أي إرشادات خاصة للمندوب للوصول للعنوان..."></textarea>
                        </div>
                        <div class="form-check form-switch form-switch-md">
                            <input class="form-check-input" type="checkbox" id="create_is_default" name="is_default" value="1" @checked($customer->addresses->count() === 0)>
                            <label class="form-check-label ms-2" for="create_is_default">تعيين كعنوان افتراضي للعميل</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ العنوان</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Customer Address -->
<div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="edit-address-form" method="POST" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="editAddressModalLabel">
                    <i class="ri-edit-line me-1 text-primary"></i> تعديل بيانات العنوان
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <!-- القسم 1: التسمية وبيانات المستلم -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-user-3-line text-primary me-1"></i> بيانات التسمية والمستلم</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="edit_label" class="form-label required">اسم العنوان / التسمية <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_label" name="label" required>
                                <div class="d-flex gap-1 mt-2 flex-wrap">
                                    <span class="text-muted fs-11 me-1 align-self-center">اقتراحات سريعة:</span>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="edit_label" data-value="المنزل">🏠 المنزل</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="edit_label" data-value="العمل">🏢 العمل</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="edit_label" data-value="شقة">🚪 شقة</button>
                                    <button type="button" class="btn btn-xs btn-outline-secondary label-preset-btn" data-target="edit_label" data-value="أخرى">📍 أخرى</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_name" class="form-label">اسم المستلم</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-user-line"></i></span>
                                    <input type="text" class="form-control" id="edit_contact_name" name="contact_name" placeholder="اسم مستلم الطلب">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_phone" class="form-label">هاتف المستلم</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                    <input type="text" class="form-control" id="edit_contact_phone" name="contact_phone" placeholder="77xxxxxxx" dir="ltr">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 2: النطاق الجغرافي وتفاصيل العنوان -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-global-line text-info me-1"></i> النطاق الجغرافي وتفاصيل العنوان</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6 col-lg-3">
                                <label for="edit_country_id" class="form-label required">الدولة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_country_id" name="country_id" required>
                                    <option value="">اختر الدولة</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name_ar }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="edit_governorate_id" class="form-label required">المحافظة <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_governorate_id" name="governorate_id" required>
                                    <option value="">اختر المحافظة</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="edit_city_id" class="form-label">المدينة</label>
                                <select class="form-select" id="edit_city_id" name="city_id">
                                    <option value="">اختر المدينة (اختياري)</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <label for="edit_district_id" class="form-label">المديرية</label>
                                <select class="form-select" id="edit_district_id" name="district_id">
                                    <option value="">اختر المديرية (اختياري)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="edit_formatted_address" class="form-label">العنوان بالتفصيل / وصف المكان</label>
                                <input type="text" class="form-control" id="edit_formatted_address" name="formatted_address" placeholder="مثال: صنعاء، شارع حدة، بجوار مركز الكميم">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_neighborhood" class="form-label">الحي / المنطقة</label>
                                <input type="text" class="form-control" id="edit_neighborhood" name="neighborhood">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_street" class="form-label">الشارع</label>
                                <input type="text" class="form-control" id="edit_street" name="street">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_landmark" class="form-label">أقرب معلم بارز</label>
                                <input type="text" class="form-control" id="edit_landmark" name="landmark">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_building" class="form-label">رقم / اسم المبنى</label>
                                <input type="text" class="form-control" id="edit_building" name="building">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_floor" class="form-label">الطابق</label>
                                <input type="text" class="form-control" id="edit_floor" name="floor">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_apartment" class="form-label">رقم الشقة</label>
                                <input type="text" class="form-control" id="edit_apartment" name="apartment">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 3: تحديد الموقع بالخريطة -->
                <div class="card border mb-3 shadow-none">
                    <div class="card-header bg-light-subtle py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold fs-13 text-dark"><i class="ri-map-pin-range-line text-danger me-1"></i> تحديد الموقع على الخريطة (GPS)</span>
                        <button type="button" class="btn btn-xs btn-outline-primary" id="btn-edit-locate-me">
                            <i class="ri-crosshair-2-line me-1"></i> موقعي الحالي
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-subtle-info fs-12 py-2 mb-2">
                            <i class="ri-information-line me-1"></i> انقر على الخريطة أو اسحب العلامة لتحديث الإحداثيات تلقائياً.
                        </div>
                        <div id="edit-address-picker-map" style="height: 240px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 12px; z-index: 1;"></div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="edit_latitude" class="form-label fs-12">خط العرض (Latitude)</label>
                                <input type="number" step="any" class="form-control form-control-sm" id="edit_latitude" name="latitude" dir="ltr">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_longitude" class="form-label fs-12">خط الطول (Longitude)</label>
                                <input type="number" step="any" class="form-control form-control-sm" id="edit_longitude" name="longitude" dir="ltr">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- القسم 4: ملاحظات وخيارات -->
                <div class="card border mb-0 shadow-none">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">ملاحظات العنوان أو تعليمات التوصيل</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="2"></textarea>
                        </div>
                        <div class="form-check form-switch form-switch-md">
                            <input class="form-check-input" type="checkbox" id="edit_is_default" name="is_default" value="1">
                            <label class="form-check-label ms-2" for="edit_is_default">تعيين كعنوان افتراضي للعميل</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@if($googleMapsWebApiKey)
<script src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsWebApiKey) }}" async defer></script>
@endif

<script>
const allGovernorates = @json($governorates);
const allCities = @json($cities);
const allDistricts = @json($districts);

function setupAddressCascade(prefix, initialValues = {}) {
    const countryEl = document.getElementById(`${prefix}_country_id`);
    const govEl = document.getElementById(`${prefix}_governorate_id`);
    const cityEl = document.getElementById(`${prefix}_city_id`);
    const distEl = document.getElementById(`${prefix}_district_id`);

    if (!countryEl || !govEl || !cityEl || !distEl) return;

    let targetGovId = initialValues.governorate_id || null;
    let targetCityId = initialValues.city_id || null;
    let targetDistrictId = initialValues.district_id || null;

    if (!countryEl.value && targetGovId) {
        const foundGov = allGovernorates.find(g => String(g.id) === String(targetGovId));
        if (foundGov) {
            countryEl.value = foundGov.country_id;
        }
    }

    function populateDistricts(cityId, selectedDistrictId = null) {
        distEl.innerHTML = '<option value="">اختر المديرية (اختياري)</option>';
        if (!cityId) return;

        const filtered = allDistricts.filter(d => String(d.city_id) === String(cityId));
        filtered.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name_ar;
            if (selectedDistrictId && String(d.id) === String(selectedDistrictId)) {
                opt.selected = true;
            }
            distEl.appendChild(opt);
        });
    }

    function populateCities(govId, selectedCityId = null) {
        cityEl.innerHTML = '<option value="">اختر المدينة (اختياري)</option>';
        distEl.innerHTML = '<option value="">اختر المديرية (اختياري)</option>';
        if (!govId) return;

        const filtered = allCities.filter(c => String(c.governorate_id) === String(govId));
        filtered.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name_ar;
            if (selectedCityId && String(c.id) === String(selectedCityId)) {
                opt.selected = true;
            }
            cityEl.appendChild(opt);
        });

        if (cityEl.value) {
            populateDistricts(cityEl.value, targetDistrictId);
        }
    }

    function populateGovernorates(countryId, selectedGovId = null) {
        govEl.innerHTML = '<option value="">اختر المحافظة</option>';
        cityEl.innerHTML = '<option value="">اختر المدينة (اختياري)</option>';
        distEl.innerHTML = '<option value="">اختر المديرية (اختياري)</option>';
        if (!countryId) return;

        const filtered = allGovernorates.filter(g => String(g.country_id) === String(countryId));
        filtered.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id;
            opt.textContent = g.name_ar;
            if (selectedGovId && String(g.id) === String(selectedGovId)) {
                opt.selected = true;
            }
            govEl.appendChild(opt);
        });

        if (govEl.value) {
            populateCities(govEl.value, targetCityId);
        }
    }

    countryEl.onchange = function () {
        targetGovId = null;
        targetCityId = null;
        targetDistrictId = null;
        populateGovernorates(this.value);
    };

    govEl.onchange = function () {
        targetCityId = null;
        targetDistrictId = null;
        populateCities(this.value);
    };

    cityEl.onchange = function () {
        targetDistrictId = null;
        populateDistricts(this.value);
    };

    if (countryEl.value) {
        populateGovernorates(countryEl.value, targetGovId);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const DEFAULT_LAT = 15.369445;
    const DEFAULT_LNG = 44.191006;

    // 1. Quick Presets for Label
    document.querySelectorAll('.label-preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            if (target) {
                target.value = this.dataset.value;
                target.focus();
            }
        });
    });

    // 2. Cascade Country to Governorate -> City -> District
    setupAddressCascade('create');

    // 3. View Address Map Modal
    const mapElement = document.getElementById('customer-address-map');
    const viewModalEl = document.getElementById('addressMapModal');
    let viewMap = null;
    let viewMarker = null;

    if (mapElement && viewModalEl && window.bootstrap) {
        const viewModal = new bootstrap.Modal(viewModalEl);
        document.querySelectorAll('.view-address-map-btn').forEach(button => {
            button.addEventListener('click', function () {
                const lat = parseFloat(this.dataset.latitude);
                const lng = parseFloat(this.dataset.longitude);
                const label = this.dataset.label || 'موقع العنوان';
                const addr = this.dataset.address || '';

                if (isNaN(lat) || isNaN(lng)) {
                    Swal.fire('تنبيه', 'لا تتوفر إحداثيات صحيحة لهذا العنوان.', 'warning');
                    return;
                }

                document.getElementById('addressMapModalLabel').innerHTML = `<i class="ri-map-2-line text-primary me-1"></i> ${label}`;
                document.getElementById('addressMapModalSub').textContent = addr;
                document.getElementById('addressMapCoords').textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                document.getElementById('addressMapGoogleLink').href = `https://www.google.com/maps?q=${lat},${lng}`;

                viewModal.show();

                viewModalEl.addEventListener('shown.bs.modal', function onShown() {
                    viewModalEl.removeEventListener('shown.bs.modal', onShown);
                    if (!viewMap) {
                        viewMap = L.map('customer-address-map').setView([lat, lng], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap'
                        }).addTo(viewMap);
                        viewMarker = L.marker([lat, lng]).addTo(viewMap);
                    } else {
                        viewMap.setView([lat, lng], 16);
                        viewMarker.setLatLng([lat, lng]);
                    }
                    viewMarker.bindPopup(`<b>${label}</b><br>${addr}`).openPopup();
                    viewMap.invalidateSize();
                }, { once: true });
            });
        });
    }

    // Helper for interactive map pickers (Create & Edit)
    function initMapPicker(containerId, modalId, latInputId, lngInputId, locateBtnId) {
        const modalEl = document.getElementById(modalId);
        const mapEl = document.getElementById(containerId);
        const latInput = document.getElementById(latInputId);
        const lngInput = document.getElementById(lngInputId);
        const locateBtn = document.getElementById(locateBtnId);
        if (!modalEl || !mapEl || !latInput || !lngInput) return null;

        let pickerMap = null;
        let pickerMarker = null;

        function setCoords(lat, lng, pan = false) {
            latInput.value = Number(lat).toFixed(6);
            lngInput.value = Number(lng).toFixed(6);
            if (pickerMarker) {
                pickerMarker.setLatLng([lat, lng]);
            }
            if (pan && pickerMap) {
                pickerMap.setView([lat, lng], pickerMap.getZoom() || 15);
            }
        }

        modalEl.addEventListener('shown.bs.modal', function () {
            let lat = parseFloat(latInput.value);
            let lng = parseFloat(lngInput.value);
            const hasCoords = !isNaN(lat) && !isNaN(lng);
            if (!hasCoords) {
                lat = DEFAULT_LAT;
                lng = DEFAULT_LNG;
            }

            if (!pickerMap) {
                pickerMap = L.map(containerId).setView([lat, lng], hasCoords ? 16 : 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(pickerMap);

                pickerMarker = L.marker([lat, lng], { draggable: true }).addTo(pickerMap);

                pickerMap.on('click', function (e) {
                    setCoords(e.latlng.lat, e.latlng.lng, false);
                });

                pickerMarker.on('dragend', function () {
                    const pos = pickerMarker.getLatLng();
                    setCoords(pos.lat, pos.lng, false);
                });
            } else {
                pickerMap.setView([lat, lng], hasCoords ? 16 : 13);
                pickerMarker.setLatLng([lat, lng]);
            }

            setTimeout(function () {
                if (pickerMap) pickerMap.invalidateSize();
            }, 200);
        });

        // Typing coordinates manually
        const onInputChange = function () {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng) && pickerMap && pickerMarker) {
                pickerMarker.setLatLng([lat, lng]);
                pickerMap.setView([lat, lng], pickerMap.getZoom() || 15);
            }
        };
        latInput.addEventListener('change', onInputChange);
        lngInput.addEventListener('change', onInputChange);

        // Geolocation "Locate Me" button
        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    Swal.fire('تنبيه', 'خاصية تحديد الموقع غير مدعومة في متصفحك.', 'warning');
                    return;
                }
                const originalHtml = locateBtn.innerHTML;
                locateBtn.disabled = true;
                locateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري التحديد...';

                navigator.geolocation.getCurrentPosition(
                    function (pos) {
                        locateBtn.disabled = false;
                        locateBtn.innerHTML = originalHtml;
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        setCoords(lat, lng, true);
                    },
                    function (err) {
                        locateBtn.disabled = false;
                        locateBtn.innerHTML = originalHtml;
                        Swal.fire('تنبيه', 'تعذر الوصول إلى موقعك الحالي. يرجى السماح بالإذن في المتصفح.', 'info');
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            });
        }

        return {
            setCoords: setCoords
        };
    }

    // Initialize map pickers for both modals
    initMapPicker('create-address-picker-map', 'createAddressModal', 'create_latitude', 'create_longitude', 'btn-create-locate-me');
    const editPicker = initMapPicker('edit-address-picker-map', 'editAddressModal', 'edit_latitude', 'edit_longitude', 'btn-edit-locate-me');

    // 4. Edit Address Button click handler
    const editButtons = document.querySelectorAll('.edit-address-btn');
    const editForm = document.getElementById('edit-address-form');
    const editModalEl = document.getElementById('editAddressModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    editButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const data = this.dataset;
            editForm.action = data.action;

            document.getElementById('edit_label').value = data.label || '';
            document.getElementById('edit_contact_name').value = data.contact_name || '';
            document.getElementById('edit_contact_phone').value = data.contact_phone || '';
            document.getElementById('edit_formatted_address').value = data.formatted_address || '';
            document.getElementById('edit_neighborhood').value = data.neighborhood || '';
            document.getElementById('edit_street').value = data.street || '';
            document.getElementById('edit_building').value = data.building || '';
            document.getElementById('edit_floor').value = data.floor || '';
            document.getElementById('edit_apartment').value = data.apartment || '';
            document.getElementById('edit_landmark').value = data.landmark || '';
            document.getElementById('edit_notes').value = data.notes || '';
            document.getElementById('edit_latitude').value = data.latitude || '';
            document.getElementById('edit_longitude').value = data.longitude || '';
            document.getElementById('edit_is_default').checked = data.is_default === '1';

            const countrySelect = document.getElementById('edit_country_id');
            countrySelect.value = data.country_id || '';

            setupAddressCascade('edit', {
                governorate_id: data.governorate_id || null,
                city_id: data.city_id || null,
                district_id: data.district_id || null
            });

            editModal.show();
        });
    });

    // 5. Delete Address Button with confirmation
    const deleteButtons = document.querySelectorAll('.delete-address-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const form = this.closest('form');
            Swal.fire({
                title: 'تأكيد حذف العنوان',
                text: 'هل أنت متأكد من حذف هذا العنوان؟ إذا كان افتراضياً سيتم اختيار عنوان بديل كافتراضي تلقائياً.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f06548',
                cancelButtonColor: '#878a99',
                confirmButtonText: 'نعم، احذف العنوان',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    // 6. Manual Phone Verification
    const verifyBtn = document.getElementById('btn-manual-verify');
    const verifyForm = document.getElementById('verify-phone-form');
    if (verifyBtn && verifyForm) {
        verifyBtn.addEventListener('click', function () {
            Swal.fire({
                title: 'تأكيد توثيق رقم الهاتف',
                text: 'هل تريد تأكيد رقم هاتف هذا العميل يدويًا؟ سيتم تسجيل عملية التوثيق باسم المستخدم الحالي.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0ab39c',
                cancelButtonColor: '#f06548',
                confirmButtonText: 'نعم، قم بالتوثيق',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed) {
                    verifyBtn.disabled = true;
                    verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جاري التوثيق...';
                    verifyForm.submit();
                }
            });
        });
    }
});
</script>
@endpush
