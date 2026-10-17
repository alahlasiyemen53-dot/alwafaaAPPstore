@extends('layouts.admin')

@section('title', 'إنشاء وإرسال إشعار | لمّة')
@section('page-title', 'إنشاء وإرسال إشعار فوري')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">إعدادات الإشعار</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.notifications.index') }}">مركز الإشعارات</a></li>
                        <li class="breadcrumb-item active">إنشاء إشعار</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-1 align-middle"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="alert-heading font-size-14 mb-2">يرجى تصحيح الأخطاء التالية:</h6>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        {{-- نموذج إنشاء الإشعار --}}
        <div class="col-xl-7">
            <form id="composeForm" method="POST" action="{{ route('admin.notifications.compose.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- 1. الجمهور المستهدف --}}
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="ri-user-shared-line text-primary font-size-18"></i>
                            الجمهور المستهدف
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4 p-3 bg-light rounded-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="audience" id="audience_all" value="all"
                                               {{ old('audience', 'all') === 'all' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="audience_all">
                                            جميع مستخدمي التطبيق
                                            <span class="badge bg-soft-primary text-primary ms-1">FCM Topic: {{ $generalTopic }}</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="audience" id="audience_segment" value="segment"
                                               {{ old('audience') === 'segment' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="audience_segment">
                                            شريحة مخصصة من العملاء (Customer Segmentation)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="audience" id="audience_customer" value="customer"
                                               {{ old('audience') === 'customer' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="audience_customer">
                                            مستخدم معيّن (جهاز مسجل)
                                        </label>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="ri-information-line me-1"></i>
                                    خيار «البث العام» يبث عبر موضوع Firebase المشترك (<code>{{ $generalTopic }}</code>) لجميع الأجهزة، بينما «الشرائح» تستهدف تلقائياً الأجهزة النشطة وفق سلوك العميل.
                                </small>
                            </div>

                            {{-- اختيار الشريحة المخصصة للعملاء --}}
                            <div class="col-12" id="segment_select_wrap" style="{{ old('audience') === 'segment' ? '' : 'display:none;' }}">
                                <label class="form-label" for="segment">اختر شريحة العملاء المستهدفة <span class="text-danger">*</span></label>
                                <select name="segment" id="segment" class="form-select">
                                    <option value="active_30_days" @selected(old('segment', 'active_30_days') === 'active_30_days')>
                                        العملاء النشطون خلال آخر 30 يوماً ({{ $segmentStats['active_30_days'] ?? 0 }} جهاز متاح)
                                    </option>
                                    <option value="inactive_30_days" @selected(old('segment') === 'inactive_30_days')>
                                        العملاء غير النشطين منذ 30 يوماً - حملة إعادة تنشيط ({{ $segmentStats['inactive_30_days'] ?? 0 }} جهاز متاح)
                                    </option>
                                    <option value="abandoned_cart" @selected(old('segment') === 'abandoned_cart')>
                                        عملاء لديهم سلات شراء متروكة ({{ $segmentStats['abandoned_cart'] ?? 0 }} جهاز متاح)
                                    </option>
                                    <option value="with_orders" @selected(old('segment') === 'with_orders')>
                                        عملاء لديهم طلبات سابقة مكتملة ({{ $segmentStats['with_orders'] ?? 0 }} جهاز متاح)
                                    </option>
                                </select>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge bg-soft-success text-success"><i class="ri-pulse-line me-1"></i>نشطون: {{ $segmentStats['active_30_days'] ?? 0 }}</span>
                                    <span class="badge bg-soft-warning text-warning"><i class="ri-user-unfollow-line me-1"></i>غير نشطين: {{ $segmentStats['inactive_30_days'] ?? 0 }}</span>
                                    <span class="badge bg-soft-info text-info"><i class="ri-shopping-cart-2-line me-1"></i>سلات متروكة: {{ $segmentStats['abandoned_cart'] ?? 0 }}</span>
                                    <span class="badge bg-soft-primary text-primary"><i class="ri-shopping-bag-3-line me-1"></i>أصحاب طلبات: {{ $segmentStats['with_orders'] ?? 0 }}</span>
                                </div>
                            </div>

                            <div class="col-12" id="customer_select_wrap" style="{{ old('audience') === 'customer' ? '' : 'display:none;' }}">
                                <label class="form-label" for="customer_id">اختر العميل المستهدف <span class="text-danger">*</span></label>
                                <select name="customer_id" id="customer_id" class="form-select">
                                    <option value="">-- ابحث أو اختر العميل --</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                            {{ $customer->name }} ({{ $customer->phone }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($customers->isEmpty())
                                    <small class="text-danger d-block mt-1">لا يوجد عملاء لديهم أجهزة FCM نشطة حالياً.</small>
                                @endif
                            </div>

                            {{-- فلترة المنصة والموقع الجغرافي --}}
                            <div class="col-12">
                                <hr class="my-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="font-size-14 mb-0 text-dark fw-bold">
                                        <i class="ri-filter-3-line text-primary me-1"></i>
                                        فلترة المنصة والموقع الجغرافي (Platform & Governorate Targeting)
                                    </h6>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-soft-info text-info"><i class="ri-apple-fill me-1"></i>iOS: {{ $segmentStats['ios_users'] ?? 0 }}</span>
                                        <span class="badge bg-soft-success text-success"><i class="ri-android-fill me-1"></i>Android: {{ $segmentStats['android_users'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="platform">
                                    <i class="ri-smartphone-line text-primary me-1"></i>
                                    نوع منصة الجهاز (نظام التشغيل)
                                </label>
                                <select name="platform" id="platform" class="form-select">
                                    <option value="all" @selected(old('platform', 'all') === 'all')>جميع المنصات (Android + iOS)</option>
                                    <option value="android" @selected(old('platform') === 'android')>أجهزة أندرويد فقط (Android)</option>
                                    <option value="ios" @selected(old('platform') === 'ios')>أجهزة آيفون فقط (Apple iOS)</option>
                                </select>
                                <small class="text-muted d-block mt-1">حصر الإشعار بمستخدمي نظام تشغيل محدد.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="governorate_id">
                                    <i class="ri-map-pin-line text-primary me-1"></i>
                                    المحافظة المستهدفة
                                </label>
                                <select name="governorate_id" id="governorate_id" class="form-select">
                                    <option value="">جميع المحافظات (الكل بدون استثناء)</option>
                                    @foreach($governorates as $gov)
                                        <option value="{{ $gov->id }}" @selected((string) old('governorate_id') === (string) $gov->id)>
                                            {{ $gov->name_ar }} ({{ $gov->name_en }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">حصر الإشعار بعملاء محافظة معينة.</small>
                            </div>

                            <div class="col-12" id="governorate_target_wrap" style="{{ old('governorate_id') ? '' : 'display:none;' }}">
                                <label class="form-label fw-semibold">
                                    <i class="ri-focus-3-line text-primary me-1"></i>
                                    معيار استهداف المحافظة
                                </label>
                                <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded-3 border">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="governorate_target" id="gov_target_any" value="any"
                                               {{ old('governorate_target', 'any') === 'any' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="gov_target_any">
                                            <strong>كلاهما (شامل):</strong> المسجلون في المحافظة أو لديهم عناوين توصيل فيها
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="governorate_target" id="gov_target_registered" value="registered"
                                               {{ old('governorate_target') === 'registered' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="gov_target_registered">
                                            <strong>المسجلون فقط:</strong> بحسب المحافظة المسجلة في حساب العميل
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="governorate_target" id="gov_target_address" value="address"
                                               {{ old('governorate_target') === 'address' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="gov_target_address">
                                            <strong>أصحاب العناوين فقط:</strong> للعملاء الذين لديهم عنوان توصيل مسجل في هذه المحافظة
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. محتوى الإشعار --}}
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="ri-message-3-line text-primary font-size-18"></i>
                            محتوى الإشعار
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0" for="title">عنوان الإشعار <span class="text-danger">*</span></label>
                                    <span class="badge bg-light text-muted font-size-11" id="title_counter">0 / 120</span>
                                </div>
                                <input type="text" name="title" id="title" class="form-control" maxlength="120"
                                       value="{{ old('title', 'عرض حصري من لمّة') }}" required placeholder="أدخل عنواناً جذاباً وموجزاً">
                                <small class="text-muted d-block mt-1">يُفضل ألا يتجاوز العنوان 50 حرفاً لتفادي قطعه في شريط الإشعارات على الهواتف.</small>
                            </div>

                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0" for="body">نص الرسالة <span class="text-danger">*</span></label>
                                    <span class="badge bg-light text-muted font-size-11" id="body_counter">0 / 500</span>
                                </div>
                                <textarea name="body" id="body" rows="4" class="form-control" maxlength="500" required
                                          placeholder="اكتب تفاصيل التنبيه أو العرض الترويجي...">{{ old('body', 'استمتع بأحدث التخفيضات والعروض المتاحة الآن في تطبيق لمّة!') }}</textarea>
                                <small class="text-muted d-block mt-1">النصوص التي تتجاوز 120 حرفاً قد تظهر مقتضبة في الإشعار قبل توسيعه من المستخدم.</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="image">صورة الإشعار (اختيارية)</label>
                                <div class="input-group">
                                    <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                    <button class="btn btn-outline-secondary d-none" type="button" id="remove_image_btn">
                                        <i class="ri-close-line"></i> إزالة
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    JPG / PNG / WebP — الحد الأقصى 2MB. يجب أن يكون نطاق التطبيق عاماً (Public HTTPS) ليتمكن محرك Firebase من جلبها.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. التوجيه الذكي داخل التطبيق --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="ri-navigation-line text-primary font-size-18"></i>
                            التوجيه الذكي داخل التطبيق (In-App Destination)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="destination_type">نوع الوجهة عند النقر</label>
                                <select name="destination_type" id="destination_type" class="form-select">
                                    <option value="none" @selected(old('destination_type', 'none') === 'none')>فتح التطبيق فقط (بدون وجهة)</option>
                                    <option value="product" @selected(old('destination_type') === 'product')>منتج محدد (Product)</option>
                                    <option value="collection" @selected(old('destination_type') === 'collection')>تشكيلة / قسم ترويجي (Collection)</option>
                                    <option value="category" @selected(old('destination_type') === 'category')>تصنيف منتجات (Category)</option>
                                    <option value="order" @selected(old('destination_type') === 'order')>رقم طلب محدد (Order)</option>
                                </select>
                            </div>

                            {{-- حقل اختيار المنتج --}}
                            <div class="col-md-7 destination-field" id="dest_wrap_product" style="display:none;">
                                <label class="form-label" for="select_product">اختر المنتج المستهدف <span class="text-danger">*</span></label>
                                <select id="select_product" class="form-select">
                                    <option value="">-- اختر المنتج --</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name_ar ?? $p->name_en }} (#{{ $p->id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- حقل اختيار المجموعة --}}
                            <div class="col-md-7 destination-field" id="dest_wrap_collection" style="display:none;">
                                <label class="form-label" for="select_collection">اختر التشكيلة المستهدفة <span class="text-danger">*</span></label>
                                <select id="select_collection" class="form-select">
                                    <option value="">-- اختر التشكيلة --</option>
                                    @foreach($collections as $col)
                                        <option value="{{ $col->id }}">{{ $col->name_ar ?? $col->name_en }} (#{{ $col->id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- حقل اختيار التصنيف --}}
                            <div class="col-md-7 destination-field" id="dest_wrap_category" style="display:none;">
                                <label class="form-label" for="select_category">اختر التصنيف المستهدف <span class="text-danger">*</span></label>
                                <select id="select_category" class="form-select">
                                    <option value="">-- اختر التصنيف --</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name_ar ?? $cat->name_en }} (#{{ $cat->id }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- حقل إدخال رقم الطلب يدوياً --}}
                            <div class="col-md-7 destination-field" id="dest_wrap_order" style="display:none;">
                                <label class="form-label" for="input_order_id">رقم معرّف الطلب (Order ID) <span class="text-danger">*</span></label>
                                <input type="number" id="input_order_id" class="form-control" placeholder="مثال: 1042" min="1">
                            </div>

                            {{-- الحقل المخفي الفعلي الذي يتم إرساله للـ Backend --}}
                            <input type="hidden" name="destination_id" id="destination_id" value="{{ old('destination_id') }}">
                        </div>
                    </div>
                </div>

                {{-- 4. توقيت الإرسال والجدولة الزمنيّة --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="ri-calendar-schedule-line text-primary font-size-18"></i>
                            توقيت الإرسال والجدولة (Delivery Timing)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-4 p-3 bg-light rounded-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="send_type" id="send_type_now" value="now"
                                               {{ old('send_type', 'now') === 'now' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="send_type_now">
                                            إرسال فوري الآن
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="send_type" id="send_type_scheduled" value="scheduled"
                                               {{ old('send_type') === 'scheduled' ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold" for="send_type_scheduled">
                                            جدولة الإرسال لموعد لاحق (Scheduled Notification)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-7" id="scheduled_wrap" style="{{ old('send_type') === 'scheduled' ? '' : 'display:none;' }}">
                                <label class="form-label" for="scheduled_at">اختر موعد الإرسال المستقبلي <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control"
                                       value="{{ old('scheduled_at') }}"
                                       min="{{ now()->addMinutes(1)->format('Y-m-d\TH:i') }}">
                                <small class="text-muted d-block mt-1">
                                    <i class="ri-time-line me-1"></i>
                                    سيتم حفظ الإشعار في طابور المعالجة وتأجيل إرساله عبر الـ Worker تلقائياً في التوقيت المختار.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- أزرار الإجراءات --}}
                <div class="d-flex align-items-center gap-2 mb-4">
                    <button type="button" id="btn_submit_compose" class="btn btn-primary px-4">
                        <i class="ri-send-plane-fill me-1" id="submit_icon"></i>
                        <span id="submit_label">إرسال الإشعار الفوري</span>
                    </button>
                    <a href="{{ route('admin.notifications.index') }}" class="btn btn-light px-3">إلغاء</a>
                </div>
            </form>
        </div>

        {{-- العمود الجانبي: المعاينة الحية للهاتف + حالة FCM --}}
        <div class="col-xl-5">
            {{-- معاينة الهاتف الذكي الحية --}}
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="ri-smartphone-line text-primary font-size-18"></i>
                        معاينة الإشعار على الهاتف
                    </h5>
                    <span class="badge bg-soft-info text-info font-size-11">معاينة تفاعلية حية</span>
                </div>
                <div class="card-body p-4 bg-light d-flex justify-content-center">
                    {{-- مجسم شاشة الهاتف المحاكية --}}
                    <div class="mobile-mockup-wrapper" style="width: 320px; background: #121824; border-radius: 36px; padding: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.25);">
                        {{-- إطار الشاشة --}}
                        <div class="mobile-screen" style="background: #1e293b; border-radius: 26px; overflow: hidden; min-height: 480px; position: relative;">
                            {{-- شريط الكاميرا العلوية (Notch) --}}
                            <div class="text-center pt-2 pb-1">
                                <span style="display: inline-block; width: 60px; height: 14px; background: #0f172a; border-radius: 10px;"></span>
                            </div>

                            {{-- شريط الحالة (Status bar) --}}
                            <div class="d-flex justify-content-between px-3 text-white-50 font-size-11 mb-3">
                                <span id="mockup_clock">10:45</span>
                                <div>
                                    <i class="ri-wifi-line me-1"></i>
                                    <i class="ri-battery-charge-line"></i>
                                </div>
                            </div>

                            {{-- لافتة الإشعار (Notification Banner) --}}
                            <div class="p-2 mx-2">
                                <div class="notification-bubble p-3" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 18px; box-shadow: 0 8px 20px rgba(0,0,0,0.15);">
                                    {{-- ترويسة الإشعار --}}
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 22px; height: 22px; font-size: 11px; font-weight: bold;">
                                                ل
                                            </span>
                                            <span class="fw-bold font-size-12 text-dark">لمّة</span>
                                            <span class="text-muted font-size-10">• الآن</span>
                                        </div>
                                        <i class="ri-arrow-down-s-line text-muted font-size-14"></i>
                                    </div>

                                    {{-- عنوان ونص الإشعار الحي --}}
                                    <div class="d-flex gap-2 align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 id="mockup_title" class="fw-bold text-dark font-size-13 mb-1" style="word-break: break-word;">
                                                عرض حصري من لمّة
                                            </h6>
                                            <p id="mockup_body" class="text-muted font-size-12 mb-0" style="line-height: 1.35; word-break: break-word;">
                                                استمتع بأحدث التخفيضات والعروض المتاحة الآن في تطبيق لمّة!
                                            </p>
                                        </div>
                                        {{-- صورة المعاينة المصغرة --}}
                                        <div id="mockup_image_wrap" class="d-none flex-shrink-0">
                                            <img id="mockup_image" src="" alt="صورة الإشعار" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px;">
                                        </div>
                                    </div>

                                    {{-- شارة الوجهة الحية --}}
                                    <div id="mockup_destination_wrap" class="mt-2 pt-2 border-top border-light d-none">
                                        <span class="badge bg-soft-primary text-primary font-size-10 d-inline-flex align-items-center gap-1">
                                            <i class="ri-external-link-line"></i>
                                            <span id="mockup_destination_text">يفتح: بدون وجهة</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- محاكاة خلفية الهاتف --}}
                            <div class="text-center text-white-50 mt-5 pt-4">
                                <i class="ri-fingerprint-2-line font-size-36 opacity-25"></i>
                                <div class="font-size-11 opacity-50 mt-2">اسحب لفتح التطبيق</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- بطاقة فحص صحة خادم FCM --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="ri-pulse-line text-success font-size-18"></i>
                        جاهزية خدمة Firebase (FCM Health)
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-3 font-size-13">
                        <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                            <span>خدمة Firebase Push:</span>
                            @if($health['enabled'])
                                <span class="badge bg-success-subtle text-success">مفعّلة</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">معطّلة</span>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                            <span>ملف اعتمادات الخدمة (Credentials):</span>
                            @if($health['has_credentials'])
                                <span class="badge bg-primary-subtle text-primary">جاهز ومتاح للقراءة</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning">غير موجود / غير متاح</span>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light">
                            <span>مُرسل FCM (Sender):</span>
                            @if($health['sender_ready'])
                                <span class="badge bg-success-subtle text-success">جاهز للإرسال</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">غير جاهز</span>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center">
                            <span>طابور المعالجة:</span>
                            <code>{{ $health['queue_connection'] }}</code> / <code>notifications</code>
                        </li>
                    </ul>

                    @if($health['enabled'] && ! $health['sender_ready'] && ! empty($health['configuration_error']))
                        <div class="alert alert-danger font-size-12 mb-3">
                            <strong>سبب التعطيل:</strong> {{ $health['configuration_error'] }}
                        </div>
                    @endif

                    <div class="alert alert-info mb-0 font-size-12">
                        <i class="ri-terminal-box-line me-1"></i>
                        <strong>ملاحظة تشغيلية:</strong> عند إرسال إشعار لعميل محدد، يتم جدولة التوصيل في طابور الخلفية:
                        <code class="d-block mt-1 font-size-11">php artisan queue:work --queue=notifications</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal تأكيد البث العام لجميع المستخدمين --}}
<div class="modal fade" id="broadcastConfirmModal" tabindex="-1" aria-labelledby="broadcastConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="broadcastConfirmModalLabel">
                    <i class="ri-alert-line me-1"></i> تأكيد البث العام لجميع المستخدمين
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold mb-2">
                    ⚠️ أنت على وشك إرسال إشعار فوري لجميع مستخدمي تطبيق «لمّة» المشتركين عبر موضوع Firebase!
                </p>
                <p class="text-muted font-size-13 mb-3">
                    لا يمكن التراجع عن عملية البث بعد الانطلاق. يرجى مراجعة ملخص الإشعار بعناية:
                </p>
                <div class="bg-light p-3 rounded-3 mb-3">
                    <div class="mb-2"><strong>العنوان:</strong> <span id="modal_summary_title">-</span></div>
                    <div class="mb-2"><strong>المحتوى:</strong> <span id="modal_summary_body">-</span></div>
                    <div class="mb-2"><strong>الهدف / القناة:</strong> <span id="modal_summary_topic"><code>{{ $generalTopic }}</code></span></div>
                    <div><strong>الوجهة داخل التطبيق:</strong> <span id="modal_summary_destination">بدون وجهة</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">تراجع وإلغاء</button>
                <button type="button" id="btn_confirm_and_send" class="btn btn-danger px-4">
                    <i class="ri-send-plane-fill me-1"></i> نعم، قم بالإرسال الآن
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('composeForm');
    const allRadio = document.getElementById('audience_all');
    const segmentRadio = document.getElementById('audience_segment');
    const customerRadio = document.getElementById('audience_customer');
    const customerWrap = document.getElementById('customer_select_wrap');
    const customerSelect = document.getElementById('customer_id');
    const segmentWrap = document.getElementById('segment_select_wrap');
    const segmentSelect = document.getElementById('segment');
    const platformSelect = document.getElementById('platform');
    const govSelect = document.getElementById('governorate_id');
    const govTargetWrap = document.getElementById('governorate_target_wrap');

    const sendTypeNow = document.getElementById('send_type_now');
    const sendTypeScheduled = document.getElementById('send_type_scheduled');
    const scheduledWrap = document.getElementById('scheduled_wrap');
    const scheduledInput = document.getElementById('scheduled_at');
    const submitIcon = document.getElementById('submit_icon');
    const submitLabel = document.getElementById('submit_label');

    const titleInput = document.getElementById('title');
    const bodyInput = document.getElementById('body');
    const titleCounter = document.getElementById('title_counter');
    const bodyCounter = document.getElementById('body_counter');

    const imageInput = document.getElementById('image');
    const removeImageBtn = document.getElementById('remove_image_btn');

    const destinationTypeSelect = document.getElementById('destination_type');
    const destinationIdInput = document.getElementById('destination_id');
    const selectProduct = document.getElementById('select_product');
    const selectCollection = document.getElementById('select_collection');
    const selectCategory = document.getElementById('select_category');
    const inputOrderId = document.getElementById('input_order_id');

    // عناصر المعاينة الحية
    const mockupTitle = document.getElementById('mockup_title');
    const mockupBody = document.getElementById('mockup_body');
    const mockupImageWrap = document.getElementById('mockup_image_wrap');
    const mockupImage = document.getElementById('mockup_image');
    const mockupDestWrap = document.getElementById('mockup_destination_wrap');
    const mockupDestText = document.getElementById('mockup_destination_text');
    const mockupClock = document.getElementById('mockup_clock');

    // ساعة الهاتف المحاكية
    function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        if (mockupClock) mockupClock.textContent = hours + ':' + minutes;
    }
    updateClock();

    // مزامنة اختيار الجمهور
    function syncAudience() {
        const isCustomer = customerRadio && customerRadio.checked;
        const isSegment = segmentRadio && segmentRadio.checked;

        if (customerWrap) customerWrap.style.display = isCustomer ? '' : 'none';
        if (segmentWrap) segmentWrap.style.display = isSegment ? '' : 'none';

        if (customerSelect) {
            if (isCustomer) {
                customerSelect.setAttribute('required', 'required');
            } else {
                customerSelect.removeAttribute('required');
            }
        }

        if (segmentSelect) {
            if (isSegment) {
                segmentSelect.setAttribute('required', 'required');
            } else {
                segmentSelect.removeAttribute('required');
            }
        }
    }

    if (allRadio) allRadio.addEventListener('change', syncAudience);
    if (segmentRadio) segmentRadio.addEventListener('change', syncAudience);
    if (customerRadio) customerRadio.addEventListener('change', syncAudience);
    syncAudience();

    // مزامنة معيار استهداف المحافظة
    function syncGovernorateTarget() {
        if (!govSelect || !govTargetWrap) return;
        govTargetWrap.style.display = govSelect.value ? '' : 'none';
    }
    if (govSelect) govSelect.addEventListener('change', syncGovernorateTarget);
    syncGovernorateTarget();

    // مزامنة توقيت الإرسال والجدولة
    function syncSchedule() {
        const isScheduled = sendTypeScheduled && sendTypeScheduled.checked;
        if (scheduledWrap) scheduledWrap.style.display = isScheduled ? '' : 'none';

        if (scheduledInput) {
            if (isScheduled) {
                scheduledInput.setAttribute('required', 'required');
            } else {
                scheduledInput.removeAttribute('required');
            }
        }

        if (submitLabel) {
            submitLabel.textContent = isScheduled ? 'حفظ وجدولة الإشعار' : 'إرسال الإشعار الفوري';
        }
        if (submitIcon) {
            submitIcon.className = isScheduled ? 'ri-calendar-check-line me-1' : 'ri-send-plane-fill me-1';
        }
    }

    if (sendTypeNow) sendTypeNow.addEventListener('change', syncSchedule);
    if (sendTypeScheduled) sendTypeScheduled.addEventListener('change', syncSchedule);
    syncSchedule();

    // عداد الحروف والتحديث الحي للعنوان والنص
    function syncCountersAndMockup() {
        const titleVal = titleInput.value.trim();
        const bodyVal = bodyInput.value.trim();

        titleCounter.textContent = titleInput.value.length + ' / 120';
        bodyCounter.textContent = bodyInput.value.length + ' / 500';

        if (titleInput.value.length > 50) {
            titleCounter.classList.replace('text-muted', 'text-warning');
        } else {
            titleCounter.classList.replace('text-warning', 'text-muted');
        }

        if (bodyInput.value.length > 120) {
            bodyCounter.classList.replace('text-muted', 'text-warning');
        } else {
            bodyCounter.classList.replace('text-warning', 'text-muted');
        }

        mockupTitle.textContent = titleVal || 'عنوان الإشعار';
        mockupBody.textContent = bodyVal || 'محتوى الرسالة...';
    }
    titleInput.addEventListener('input', syncCountersAndMockup);
    bodyInput.addEventListener('input', syncCountersAndMockup);
    syncCountersAndMockup();

    // معاينة الصورة الحية
    imageInput.addEventListener('change', function () {
        const file = imageInput.files && imageInput.files[0];
        if (!file) {
            mockupImageWrap.classList.add('d-none');
            mockupImage.removeAttribute('src');
            removeImageBtn.classList.add('d-none');
            return;
        }

        const url = URL.createObjectURL(file);
        mockupImage.src = url;
        mockupImageWrap.classList.remove('d-none');
        removeImageBtn.classList.remove('d-none');
    });

    removeImageBtn.addEventListener('click', function () {
        imageInput.value = '';
        mockupImageWrap.classList.add('d-none');
        mockupImage.removeAttribute('src');
        removeImageBtn.classList.add('d-none');
    });

    // إدارة حقول الوجهات والتوجيه الذكي
    const destWraps = {
        product: document.getElementById('dest_wrap_product'),
        collection: document.getElementById('dest_wrap_collection'),
        category: document.getElementById('dest_wrap_category'),
        order: document.getElementById('dest_wrap_order'),
    };

    function syncDestinationFields() {
        const type = destinationTypeSelect.value;

        // إخفاء كافة الحقول أولاً
        Object.values(destWraps).forEach(wrap => {
            if (wrap) wrap.style.display = 'none';
        });

        if (type === 'none') {
            destinationIdInput.value = '';
            mockupDestWrap.classList.add('d-none');
            return;
        }

        if (destWraps[type]) {
            destWraps[type].style.display = '';
        }

        updateDestinationId();
    }

    function updateDestinationId() {
        const type = destinationTypeSelect.value;
        let idVal = '';
        let labelText = '';

        if (type === 'product') {
            idVal = selectProduct.value;
            const opt = selectProduct.options[selectProduct.selectedIndex];
            labelText = 'منتج: ' + (opt && opt.value ? opt.text : '');
        } else if (type === 'collection') {
            idVal = selectCollection.value;
            const opt = selectCollection.options[selectCollection.selectedIndex];
            labelText = 'تشكيلة: ' + (opt && opt.value ? opt.text : '');
        } else if (type === 'category') {
            idVal = selectCategory.value;
            const opt = selectCategory.options[selectCategory.selectedIndex];
            labelText = 'تصنيف: ' + (opt && opt.value ? opt.text : '');
        } else if (type === 'order') {
            idVal = inputOrderId.value.trim();
            labelText = 'طلب رقم: #' + idVal;
        }

        destinationIdInput.value = idVal;

        if (type !== 'none' && idVal) {
            mockupDestWrap.classList.remove('d-none');
            mockupDestText.textContent = 'يفتح: ' + labelText;
        } else {
            mockupDestWrap.classList.add('d-none');
        }
    }

    destinationTypeSelect.addEventListener('change', syncDestinationFields);
    selectProduct.addEventListener('change', updateDestinationId);
    selectCollection.addEventListener('change', updateDestinationId);
    selectCategory.addEventListener('change', updateDestinationId);
    inputOrderId.addEventListener('input', updateDestinationId);

    // إذا كان هناك قيمة قديمة old() لـ destination_id
    const initialOldId = "{{ old('destination_id') }}";
    const initialOldType = "{{ old('destination_type', 'none') }}";
    if (initialOldType !== 'none' && initialOldId) {
        if (initialOldType === 'product') selectProduct.value = initialOldId;
        if (initialOldType === 'collection') selectCollection.value = initialOldId;
        if (initialOldType === 'category') selectCategory.value = initialOldId;
        if (initialOldType === 'order') inputOrderId.value = initialOldId;
    }
    syncDestinationFields();

    // تأكيد الإرسال وفتح Modal للبث العام
    const btnSubmit = document.getElementById('btn_submit_compose');
    const modalElement = document.getElementById('broadcastConfirmModal');
    const modal = new bootstrap.Modal(modalElement);
    const btnConfirmAndSend = document.getElementById('btn_confirm_and_send');

    btnSubmit.addEventListener('click', function () {
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const isCustomer = customerRadio && customerRadio.checked;
        if (isCustomer && !customerSelect.value) {
            alert('يرجى اختيار العميل المستهدف أولاً.');
            customerSelect.focus();
            return;
        }

        const isSegment = segmentRadio && segmentRadio.checked;
        if (isSegment && !segmentSelect.value) {
            alert('يرجى اختيار شريحة العملاء المستهدفة.');
            segmentSelect.focus();
            return;
        }

        const isScheduled = sendTypeScheduled && sendTypeScheduled.checked;
        if (isScheduled && !scheduledInput.value) {
            alert('يرجى تحديد موعد وتاريخ الجدولة للإشعار.');
            scheduledInput.focus();
            return;
        }

        const destType = destinationTypeSelect.value;
        if (destType !== 'none' && !destinationIdInput.value) {
            alert('يرجى تحديد العنصر المستهدف للوجهة المختارة.');
            return;
        }

        if (allRadio && allRadio.checked) {
            const platformVal = platformSelect ? platformSelect.value : 'all';
            const govVal = govSelect ? govSelect.value : '';
            const isFiltered = platformVal !== 'all' || govVal !== '';

            // إظهار نافذة التأكيد للبث العام أو المستهدف
            document.getElementById('modal_summary_title').textContent = titleInput.value;
            document.getElementById('modal_summary_body').textContent = bodyInput.value;
            document.getElementById('modal_summary_destination').textContent =
                destType === 'none' ? 'فتح التطبيق فقط' : (mockupDestText.textContent.replace('يفتح: ', ''));

            let targetDesc = 'موضوع Firebase العام ({{ $generalTopic }}) لجميع الأجهزة المشتركة';
            if (isFiltered) {
                const platLabel = platformVal === 'all' ? 'جميع المنصات' : (platformVal === 'ios' ? 'أجهزة Apple iOS فقط' : 'أجهزة Android فقط');
                const govLabel = govVal ? (govSelect.options[govSelect.selectedIndex]?.text || 'محافظة محددة') : 'جميع المحافظات';
                targetDesc = 'استهداف مفلتر: ' + platLabel + ' | ' + govLabel;
            }
            const modalTopicElem = document.getElementById('modal_summary_topic');
            if (modalTopicElem) modalTopicElem.textContent = targetDesc;

            if (isScheduled) {
                btnConfirmAndSend.innerHTML = '<i class="ri-calendar-check-line me-1"></i> نعم، قم بجدولة الإشعار';
            } else {
                btnConfirmAndSend.innerHTML = '<i class="ri-send-plane-fill me-1"></i> نعم، قم بالإرسال الآن';
            }

            modal.show();
        } else {
            // إرسال أو جدولة للعميل أو الشريحة
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = isScheduled
                ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري جدولة الإشعار...'
                : '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري الإرسال...';
            form.submit();
        }
    });

    btnConfirmAndSend.addEventListener('click', function () {
        btnConfirmAndSend.disabled = true;
        const isScheduled = sendTypeScheduled && sendTypeScheduled.checked;
        btnConfirmAndSend.innerHTML = isScheduled
            ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري جدولة البث العام...'
            : '<span class="spinner-border spinner-border-sm me-1" role="status"></span> جاري الإرسال للبث العام...';
        form.submit();
    });
});
</script>
@endpush
