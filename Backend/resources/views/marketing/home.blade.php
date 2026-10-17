@extends('marketing.layout')

@section('content')
<section class="hero">
    <div class="container hero-inner">
        <p class="hero-kicker">AL-Wafa'a Store</p>
        <p class="hero-brand">AW</p>
        <h1>فن الفخامة والجمال بلمسات لا تُنسى</h1>
        <p>مستحضرات استثنائية وعطور مختارة بعناية، في تطبيق يحوّل الاكتشاف إلى طلب منظم وتوصيل موثوق.</p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="{{ route('marketing.about') }}">تعرّف على المنصة</a>
            <a class="btn btn-ghost" href="{{ route('marketing.contact') }}">تواصل معنا</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">لماذا نحن</p>
            <h2>تجربة تجميل رقمية بهوية واضحة</h2>
            <p>صمّمنا الوفاء استور لتكون واجهتك لاكتشاف المنتجات الحقيقية، مع مسار طلب واضح من السلة حتى التسليم.</p>
        </div>
        <ul class="feature-list">
            <li class="reveal">
                <h3>كتالوج بعناية انتقائية</h3>
                <p>منتجات تجميل وعطور معروضة بتفاصيل واضحة تساعد على الاختيار بثقة دون ضوضاء تسويقية.</p>
            </li>
            <li class="reveal">
                <h3>طلب منظم من البداية</h3>
                <p>أضف ما تريد، اختر عناصرك للدفع، وأكمل الطلب بخطوات بسيطة داخل التطبيق.</p>
            </li>
            <li class="reveal">
                <h3>متابعة حتى بابك</h3>
                <p>تتبع مراحل الشحنة، واستلم تحديثات الحالة، وابقَ على تواصل مع الدعم عند الحاجة.</p>
            </li>
        </ul>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">عالم الجمال</p>
            <h2>ما ستجده في المنصة</h2>
            <p>مساحات اكتشاف تلهم إطلالتك اليومية والمناسبات الخاصة.</p>
        </div>
        <ul class="feature-list">
            <li class="reveal">
                <h3>العناية والبشرة</h3>
                <p>روتينات ومنتجات مختارة لتمنحك لمسة نضارة هادئة ومتناسقة.</p>
            </li>
            <li class="reveal">
                <h3>المكياج والإطلالة</h3>
                <p>ألوان وتفاصيل تساعدك على بناء إطلالة تعبّر عنك بدقة وأناقة.</p>
            </li>
            <li class="reveal">
                <h3>العطور واللمسات الأخيرة</h3>
                <p>روائح منتقاة تُكمل الحضور وتبقى في الذاكرة بعد انصراف اللحظة.</p>
            </li>
        </ul>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">كيف تعمل</p>
            <h2>من الاكتشاف إلى التوصيل في ثلاث خطوات</h2>
            <p>مسار بسيط صُمم لراحة العميل دون تعقيد.</p>
        </div>
        <ol class="steps">
            <li class="reveal">
                <h3>اكتشف</h3>
                <p>تصفّح المنتجات والمجموعات والعروض داخل تطبيق الوفاء استور.</p>
            </li>
            <li class="reveal">
                <h3>اطلب</h3>
                <p>أتمم السلة والعنوان والدفع بطريقة واضحة وآمنة.</p>
            </li>
            <li class="reveal">
                <h3>استلم</h3>
                <p>تابع شحنتك وتلقَّ طلبك مع تجربة دعم جاهزة عند الحاجة.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head reveal">
            <p class="eyebrow">ثقة وخصوصية</p>
            <h2>بياناتك جزء من احترامنا لك</h2>
            <p>نعامل معلومات الحساب والطلبات بعناية، ونوضح كيف تُستخدم في سياسة الخصوصية وتعليمات حذف الحساب.</p>
        </div>
        <div class="hero-actions reveal">
            <a class="btn btn-outline" href="{{ route('marketing.privacy') }}">اقرأ سياسة الخصوصية</a>
            <a class="btn btn-outline" href="{{ route('marketing.delete-account') }}">تعليمات حذف الحساب</a>
        </div>
    </div>
</section>

<div class="container">
    <div class="cta-band reveal">
        <h2>الجمال يستحق تجربة منظمة</h2>
        <p>حمّل تطبيق الوفاء استور وابدأ رحلتك من الاكتشاف إلى الإطلالة.</p>
        <div class="hero-actions">
            <a class="btn btn-ghost" href="{{ route('marketing.contact') }}">اسأل فريق الدعم</a>
            <a class="btn btn-primary" href="{{ route('marketing.about') }}" style="background:#fff7fa;color:#800020;">قصتنا</a>
        </div>
    </div>
</div>
@endsection
