@extends('layouts.admin')
@section('title', 'الإعدادات | لمّة')
@section('page-title', 'الإعدادات')
@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        @php($canUpdateSettings = auth()->user()->hasPermission('admin.settings.update'))
        @unless($canUpdateSettings)
            <div class="alert alert-info">يمكنك عرض الإعدادات فقط. تواصل مع مسؤول النظام لتعديلها.</div>
        @endunless

        <form method="POST" action="{{ route('admin.master-data.settings.update') }}">
            @csrf
            @method('PUT')
            <fieldset @disabled(!$canUpdateSettings)>
                <!-- Basic Settings Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title mb-0"><i class="ri-settings-3-line me-1 text-primary"></i> الإعدادات الأساسية</h2>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="application_name" class="form-label">اسم التطبيق</label>
                                <input class="form-control @error('settings.application_name') is-invalid @enderror" id="application_name" name="settings[application_name]" value="{{ old('settings.application_name', $settings['application_name'] ?? '') }}">
                                @error('settings.application_name')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="default_locale" class="form-label">اللغة الافتراضية</label>
                                <select class="form-control @error('settings.default_locale') is-invalid @enderror" data-choices data-choices-search-false id="default_locale" name="settings[default_locale]">
                                    <option value="ar" @selected(old('settings.default_locale', $settings['default_locale'] ?? 'ar') === 'ar')>العربية</option>
                                    <option value="en" @selected(old('settings.default_locale', $settings['default_locale'] ?? 'ar') === 'en')>English</option>
                                </select>
                                @error('settings.default_locale')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="default_currency_id" class="form-label required">العملة الأساسية للنظام (Base Currency) / العملة الافتراضية <span class="badge bg-primary-subtle text-primary fs-11">المصدر الوحيد المعتمد</span></label>
                                <select class="form-control @error('settings.default_currency_id') is-invalid @enderror" data-choices id="default_currency_id" name="settings[default_currency_id]" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency->id }}" @selected((string) old('settings.default_currency_id', $settings['default_currency_id'] ?? '') === (string) $currency->id)>{{ $currency->code }} — {{ $currency->name_ar }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text fs-12 text-muted">المرجع الحصري لجميع أسعار الكتالوج، قيود الحسابات، والخزينة. لا يمكن تعديلها بعد وجود معاملات مالية.</div>
                                @error('settings.default_currency_id')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="support_email" class="form-label">بريد الدعم</label>
                                <input type="email" class="form-control @error('settings.support_email') is-invalid @enderror" id="support_email" name="settings[support_email]" value="{{ old('settings.support_email', $settings['support_email'] ?? '') }}" dir="ltr">
                                @error('settings.support_email')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gift Wrapping Settings Card -->
                <div class="card mb-4 border border-primary-subtle shadow-sm">
                    <div class="card-header bg-primary-subtle d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 text-primary">
                            <i class="ri-gift-line me-1"></i> خدمة تغليف الهدايا (Gift Wrapping Service)
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-12">
                            <i class="ri-check-line me-1"></i> خدمة مجانية (Free Service) V1
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start mb-4">
                            <i class="ri-information-line fs-18 me-2 mt-1 flex-shrink-0"></i>
                            <div class="fs-13">
                                <strong>قاعدة العمل المعتمدة:</strong> خدمة تغليف الهدايا مجانية بالكامل في هذا الإصدار (V1) ولا تترتب عليها أي رسوم إضافية على العميل أو قيود محاسبية في الخزينة أو قيود اليومية العامة.
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-check form-switch form-switch-md">
                                    <input type="hidden" name="settings[gift_wrapping_enabled]" value="0">
                                    <input class="form-check-input @error('settings.gift_wrapping_enabled') is-invalid @enderror" 
                                           type="checkbox" 
                                           role="switch" 
                                           id="gift_wrapping_enabled" 
                                           name="settings[gift_wrapping_enabled]" 
                                           value="1" 
                                           @checked(old('settings.gift_wrapping_enabled', filter_var($settings['gift_wrapping_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)))>
                                    <label class="form-check-label fw-bold" for="gift_wrapping_enabled">
                                        تفعيل خدمة تغليف الهدايا
                                    </label>
                                    <div class="form-text fs-12 text-muted">إتاحة خيار تغليف الطلب كهدية للعملاء وإدارة العمليات.</div>
                                </div>
                                @error('settings.gift_wrapping_enabled')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch form-switch-md">
                                    <input type="hidden" name="settings[gift_message_enabled]" value="0">
                                    <input class="form-check-input @error('settings.gift_message_enabled') is-invalid @enderror" 
                                           type="checkbox" 
                                           role="switch" 
                                           id="gift_message_enabled" 
                                           name="settings[gift_message_enabled]" 
                                           value="1" 
                                           @checked(old('settings.gift_message_enabled', filter_var($settings['gift_message_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)))>
                                    <label class="form-check-label fw-bold" for="gift_message_enabled">
                                        السماح بإرفاق بطاقة رسالة الهدية
                                    </label>
                                    <div class="form-text fs-12 text-muted">تمكين كتابة رسالة نصية مخصصة للهدية (تعمل فقط في حال تفعيل تغليف الهدايا).</div>
                                </div>
                                @error('settings.gift_message_enabled')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="gift_message_max_length" class="form-label fw-semibold">
                                    الحد الأقصى لطول رسالة الهدية (حرف)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-text"></i></span>
                                    <input type="number" 
                                           min="10" 
                                           max="1000" 
                                           class="form-control @error('settings.gift_message_max_length') is-invalid @enderror" 
                                           id="gift_message_max_length" 
                                           name="settings[gift_message_max_length]" 
                                           value="{{ old('settings.gift_message_max_length', $settings['gift_message_max_length'] ?? 250) }}">
                                </div>
                                <div class="form-text fs-12 text-muted">مسموح بقيم بين 10 و 1000 حرف. القيمة الافتراضية الموصى بها هي 250 حرفاً.</div>
                                @error('settings.gift_message_max_length')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Support Settings Card -->
                <div class="card mb-4 border border-info-subtle shadow-sm">
                    <div class="card-header bg-info-subtle d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 text-info">
                            <i class="ri-customer-service-2-line me-1"></i> خدمة وتواصل دعم العملاء (Customer Support Contact)
                        </h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fs-12">
                            <i class="ri-shield-check-line me-1"></i> المصدر المعتمد للتواصل
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border d-flex align-items-start mb-4">
                            <i class="ri-information-line fs-18 text-info me-2 mt-1 flex-shrink-0"></i>
                            <div class="fs-13">
                                <strong>قنوات الاتصال المباشرة:</strong> إعداد الأرقام الرسمية لقنوات الاتصال الهاتفي ومحادثات واتساب وأوقات الدوام. يتم إنشاء الروابط الآمنة (Deep Links) تلقائياً بصيغ معيارية لمنع أخطاء الروابط في تطبيقات العملاء.
                            </div>
                        </div>

                        <!-- Phone Call Support Section -->
                        <div class="border rounded p-3 mb-4 bg-light-subtle">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch form-switch-md">
                                        <input type="hidden" name="settings[support_phone_enabled]" value="0">
                                        <input class="form-check-input @error('settings.support_phone_enabled') is-invalid @enderror" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="support_phone_enabled" 
                                               name="settings[support_phone_enabled]" 
                                               value="1" 
                                               @checked(old('settings.support_phone_enabled', filter_var($settings['support_phone_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)))>
                                        <label class="form-check-label fw-bold" for="support_phone_enabled">
                                            تفعيل الاتصال الهاتفي
                                        </label>
                                        <div class="form-text fs-12 text-muted">إتاحة زر الاتصال الصوتي المباشر للعملاء.</div>
                                    </div>
                                    @error('settings.support_phone_enabled')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-md-8">
                                    <label for="support_phone" class="form-label fw-semibold">رقم هاتف الدعم (موبايل أو ثابت)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-phone-line"></i></span>
                                        <input type="text" 
                                               class="form-control @error('settings.support_phone') is-invalid @enderror" 
                                               id="support_phone" 
                                               name="settings[support_phone]" 
                                               value="{{ old('settings.support_phone', $settings['support_phone'] ?? '') }}" 
                                               placeholder="مثال: 770000000 أو +967 1 234567" 
                                               dir="ltr">
                                    </div>
                                    <div class="form-text fs-12 text-muted">يدعم التنسيقات اليمنية المعتمدة (+967).</div>
                                    @error('settings.support_phone')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                                    
                                    <div class="mt-2">
                                        <small class="text-muted fw-semibold me-1">معاينة رابط الاتصال المولد:</small>
                                        @if(!empty($customerSupport['tel_link']))
                                            <span class="badge bg-success-subtle text-success fs-12" dir="ltr">
                                                <i class="ri-external-link-line me-1"></i>{{ $customerSupport['tel_link'] }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-muted fs-12">غير مفعل / غير متوفر</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WhatsApp Support Section -->
                        <div class="border rounded p-3 mb-4 bg-light-subtle">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check form-switch form-switch-md">
                                        <input type="hidden" name="settings[support_whatsapp_enabled]" value="0">
                                        <input class="form-check-input @error('settings.support_whatsapp_enabled') is-invalid @enderror" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="support_whatsapp_enabled" 
                                               name="settings[support_whatsapp_enabled]" 
                                               value="1" 
                                               @checked(old('settings.support_whatsapp_enabled', filter_var($settings['support_whatsapp_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)))>
                                        <label class="form-check-label fw-bold" for="support_whatsapp_enabled">
                                            تفعيل محادثات واتساب
                                        </label>
                                        <div class="form-text fs-12 text-muted">إتاحة زر فتح محادثة فورية عبر واتساب.</div>
                                    </div>
                                    @error('settings.support_whatsapp_enabled')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-md-8">
                                    <label for="support_whatsapp_number" class="form-label fw-semibold">رقم واتساب المعتمد</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="ri-whatsapp-line text-success"></i></span>
                                        <input type="text" 
                                               class="form-control @error('settings.support_whatsapp_number') is-invalid @enderror" 
                                               id="support_whatsapp_number" 
                                               name="settings[support_whatsapp_number]" 
                                               value="{{ old('settings.support_whatsapp_number', $settings['support_whatsapp_number'] ?? '') }}" 
                                               placeholder="مثال: 770000000 أو 967770000000" 
                                               dir="ltr">
                                    </div>
                                    <div class="form-text fs-12 text-muted">يتم تحويله تلقائياً لصيغة الأرقام الصافية لرابط واتساب الدولي.</div>
                                    @error('settings.support_whatsapp_number')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                                </div>

                                <div class="col-12">
                                    <label for="support_whatsapp_default_message" class="form-label fw-semibold">الرسالة التمهيدية التلقائية لواتساب (Default Message)</label>
                                    <textarea class="form-control @error('settings.support_whatsapp_default_message') is-invalid @enderror" 
                                              id="support_whatsapp_default_message" 
                                              name="settings[support_whatsapp_default_message]" 
                                              rows="2" 
                                              maxlength="500" 
                                              placeholder="مثال: مرحباً فريق خدمة عملاء لمّة، أود الاستفسار بخصوص...">{{ old('settings.support_whatsapp_default_message', $settings['support_whatsapp_default_message'] ?? '') }}</textarea>
                                    <div class="d-flex justify-content-between form-text fs-12 text-muted">
                                        <span>رسالة مسبقة تظهر للمستخدم عند فتح المحادثة، بحد أقصى 500 حرف.</span>
                                    </div>
                                    @error('settings.support_whatsapp_default_message')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                                </div>

                                <div class="col-12">
                                    <div class="p-2 border rounded bg-white">
                                        <small class="text-muted fw-semibold me-1">معاينة رابط واتساب المباشر المولد (Deep Link):</small>
                                        @if(!empty($customerSupport['whatsapp_deep_link']))
                                            <a href="{{ $customerSupport['whatsapp_deep_link'] }}" target="_blank" rel="noopener noreferrer" class="badge bg-success-subtle text-success fs-12 text-wrap text-break" dir="ltr">
                                                <i class="ri-whatsapp-line me-1"></i>{{ $customerSupport['whatsapp_deep_link'] }}
                                            </a>
                                        @else
                                            <span class="badge bg-secondary-subtle text-muted fs-12">غير مفعل / غير متوفر</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Working Hours Section -->
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="support_hours_text_ar" class="form-label fw-semibold">ساعات وأوقات العمل المعتمدة</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="ri-time-line"></i></span>
                                    <input type="text" 
                                           class="form-control @error('settings.support_hours_text_ar') is-invalid @enderror" 
                                           id="support_hours_text_ar" 
                                           name="settings[support_hours_text_ar]" 
                                           value="{{ old('settings.support_hours_text_ar', $settings['support_hours_text_ar'] ?? '') }}" 
                                           placeholder="مثال: يومياً من 9:00 صباحاً حتى 11:00 مساءً" 
                                           maxlength="255">
                                </div>
                                <div class="form-text fs-12 text-muted">نص توضيحي يظهر للعملاء في التطبيق يحدد مواعيد استجابة فريق الدعم.</div>
                                @error('settings.support_hours_text_ar')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="customer_delivery_confirm_hours" class="form-label fw-semibold">مهلة تأكيد استلام العميل (بالساعات)</label>
                                <input type="number"
                                       min="1"
                                       max="168"
                                       class="form-control @error('settings.customer_delivery_confirm_hours') is-invalid @enderror"
                                       id="customer_delivery_confirm_hours"
                                       name="settings[customer_delivery_confirm_hours]"
                                       value="{{ old('settings.customer_delivery_confirm_hours', $settings['customer_delivery_confirm_hours'] ?? 24) }}">
                                <div class="form-text fs-12 text-muted">بعد تأكيد المندوب للتسليم، إن لم يؤكد العميل خلال هذه المدة يُغلق الطلب تلقائياً كمكتمل.</div>
                                @error('settings.customer_delivery_confirm_hours')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer App Update Gate -->
                <div class="card mb-4 border border-warning-subtle shadow-sm">
                    <div class="card-header bg-warning-subtle d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0 text-warning-emphasis">
                            <i class="ri-refresh-line me-1"></i> تحديثات تطبيق العملاء (Force / Optional Update)
                        </h5>
                        <span class="badge bg-dark-subtle text-dark border px-2 py-1 fs-12">
                            SemVer: minor/major = إجباري · patch/build = اختياري
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border d-flex align-items-start mb-4">
                            <i class="ri-information-line fs-18 text-warning me-2 mt-1 flex-shrink-0"></i>
                            <div class="fs-13">
                                <strong>تلقائي:</strong> يُحدَّث آخر إصدار من <code dir="ltr">apk/pubspec.yaml</code> عند النشر، أو عند فتح تطبيق بنسخة أحدث.
                                اترك الحقل فارغاً للاستخدام العادي. عبّه فقط كتجاوز يدوي للطوارئ.
                                القاعدة: minor/major أعلى = إجباري · patch/build أعلى = اختياري.
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="app_latest_version" class="form-label fw-semibold">آخر إصدار (اختياري / تجاوز)</label>
                                <input type="text"
                                       class="form-control @error('settings.app_latest_version') is-invalid @enderror"
                                       id="app_latest_version"
                                       name="settings[app_latest_version]"
                                       value="{{ old('settings.app_latest_version', $settings['app_latest_version'] ?? '') }}"
                                       placeholder="يُملأ تلقائياً — اتركه فارغاً"
                                       dir="ltr">
                                @error('settings.app_latest_version')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="app_min_version" class="form-label fw-semibold">الحد الأدنى للإصدار (إجباري لما قبله)</label>
                                <input type="text"
                                       class="form-control @error('settings.app_min_version') is-invalid @enderror"
                                       id="app_min_version"
                                       name="settings[app_min_version]"
                                       value="{{ old('settings.app_min_version', $settings['app_min_version'] ?? '') }}"
                                       placeholder="مثال: 1.1.0"
                                       dir="ltr">
                                @error('settings.app_min_version')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check form-switch pt-4">
                                    <input type="hidden" name="settings[app_force_update]" value="0">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           id="app_force_update"
                                           name="settings[app_force_update]"
                                           value="1"
                                           {{ old('settings.app_force_update', $settings['app_force_update'] ?? false) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold ms-2" for="app_force_update">
                                        فرض التحديث الإجباري دائماً
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="app_android_store_url" class="form-label fw-semibold">رابط Google Play</label>
                                <input type="url"
                                       class="form-control @error('settings.app_android_store_url') is-invalid @enderror"
                                       id="app_android_store_url"
                                       name="settings[app_android_store_url]"
                                       value="{{ old('settings.app_android_store_url', $settings['app_android_store_url'] ?? 'https://play.google.com/store/apps/details?id=com.alwafaa.lamma_app') }}"
                                       dir="ltr">
                                @error('settings.app_android_store_url')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="app_ios_store_url" class="form-label fw-semibold">رابط App Store</label>
                                <input type="url"
                                       class="form-control @error('settings.app_ios_store_url') is-invalid @enderror"
                                       id="app_ios_store_url"
                                       name="settings[app_ios_store_url]"
                                       value="{{ old('settings.app_ios_store_url', $settings['app_ios_store_url'] ?? '') }}"
                                       placeholder="https://apps.apple.com/app/id…"
                                       dir="ltr">
                                @error('settings.app_ios_store_url')<span class="field-error text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            @if($canUpdateSettings)
                <div class="form-actions mt-4 mb-5 text-end">
                    <button class="btn btn-primary px-4 py-2" type="submit">
                        <i class="ri-save-line me-1"></i> حفظ كافة الإعدادات
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
