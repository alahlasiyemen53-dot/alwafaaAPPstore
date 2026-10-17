@extends('marketing.layout')

@section('content')
<section class="page-hero">
    <div class="container">
        <h1>تواصل معنا</h1>
        <p>فريق مستشاري التجميل والدعم جاهز للاستماع — عبر القنوات أدناه مباشرة.</p>
    </div>
</section>

<section class="prose">
    <div class="container">
        <p class="reveal">
            لا نوفّر نموذجاً للإرسال من هذا الموقع. اختر القناة الأنسب لك وسنتواصل معك خلال ساعات العمل.
        </p>

        @if (!empty($hoursText))
            <p class="note reveal">ساعات العمل: {{ $hoursText }}</p>
        @endif

        <div class="contact-channels">
            @if (!empty($telLink) && !empty($phone))
                <a class="contact-channel reveal" href="{{ $telLink }}">
                    <strong>الاتصال الهاتفي</strong>
                    <span>{{ $phone }}</span>
                </a>
            @else
                <div class="contact-channel reveal">
                    <strong>الاتصال الهاتفي</strong>
                    <span>فعّل رقم الدعم من إعدادات المنصة أو تواصل عبر واتساب والبريد.</span>
                </div>
            @endif

            @if (!empty($whatsappLink) && !empty($whatsappNumber))
                <a class="contact-channel reveal" href="{{ $whatsappLink }}" target="_blank" rel="noopener noreferrer">
                    <strong>المحادثة عبر واتساب</strong>
                    <span>{{ $whatsappNumber }}</span>
                </a>
            @else
                <div class="contact-channel reveal">
                    <strong>المحادثة عبر واتساب</strong>
                    <span>قناة واتساب غير مفعّلة حالياً — جرّب البريد أو التطبيق.</span>
                </div>
            @endif

            <a class="contact-channel reveal" href="mailto:{{ $fallbackEmail }}">
                <strong>البريد الإلكتروني</strong>
                <span>{{ $fallbackEmail }}</span>
            </a>
        </div>

        <h2 class="reveal">ماذا يمكننا مساعدتك به؟</h2>
        <ul class="reveal">
            <li>استفسارات عن المنتجات والتوصيل داخل التطبيق.</li>
            <li>متابعة حالة طلب قائم.</li>
            <li>طلبات متعلقة بالحساب أو الخصوصية أو حذف الحساب.</li>
        </ul>

        <p class="note reveal">
            للدعم الأسرع من داخل التطبيق: افتح <strong>حسابي ← المساعدة وخدمة العملاء</strong>.
        </p>
    </div>
</section>
@endsection
