<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طباعة طلب العميل {{ $order->order_number }} | {{ $appName }}</title>
    <style>
        :root {
            --ink: #1a1a1a;
            --muted: #5c5c5c;
            --line: #d9d9d9;
            --soft: #f7f7f7;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            color: var(--ink);
            background: #e9ecef;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.55;
        }
        .toolbar {
            max-width: 900px;
            margin: 0 auto 16px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .toolbar a,
        .toolbar button {
            border: 1px solid #ced4da;
            background: #fff;
            color: #212529;
            border-radius: 8px;
            padding: 8px 14px;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
        }
        .toolbar button.primary {
            background: #0c2925;
            border-color: #0c2925;
            color: #fff;
        }
        .sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 28px 32px;
            box-shadow: 0 8px 24px rgba(0,0,0,.06);
        }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .brand h1 {
            margin: 0 0 4px;
            font-size: 26px;
        }
        .brand p {
            margin: 0;
            color: var(--muted);
        }
        .meta {
            text-align: left;
            min-width: 220px;
        }
        .meta .order-no {
            font-size: 18px;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 2px 10px;
            background: var(--soft);
            font-size: 12px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 18px;
        }
        .box {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            background: #fff;
        }
        .box h3 {
            margin: 0 0 8px;
            font-size: 13px;
            color: var(--muted);
            font-weight: 700;
        }
        .box .value {
            font-size: 14px;
            font-weight: 600;
        }
        .box .muted {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 16px;
        }
        th, td {
            border: 1px solid var(--line);
            padding: 8px 10px;
            vertical-align: top;
        }
        th {
            background: var(--soft);
            font-size: 12px;
            text-align: right;
        }
        td.num, th.num { text-align: left; white-space: nowrap; }
        td.center, th.center { text-align: center; }
        .totals {
            width: 320px;
            margin-right: auto;
        }
        .totals td {
            border: none;
            padding: 4px 0;
        }
        .totals tr.total td {
            border-top: 2px solid var(--ink);
            padding-top: 8px;
            font-size: 16px;
            font-weight: 800;
        }
        .notes {
            border: 1px dashed var(--line);
            border-radius: 10px;
            padding: 12px;
            margin-top: 8px;
            background: var(--soft);
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
            margin-top: 36px;
        }
        .sig {
            text-align: center;
            padding-top: 42px;
            border-top: 1px solid var(--ink);
            color: var(--muted);
            font-size: 12px;
        }
        .footer {
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .sheet {
                box-shadow: none;
                border: none;
                border-radius: 0;
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
@php
    $currencySymbol = ($order->currency?->code === 'YER')
        ? 'ر.ي'
        : ($order->currency?->symbol ?? $order->currency?->code ?? '');
    $decimals = (int) ($order->currency?->decimal_places ?? 2);
    $fmt = fn ($amount) => number_format((float) $amount, $decimals).' '.$currencySymbol;
@endphp

<div class="toolbar no-print">
    <a href="{{ route('admin.orders.show', $order) }}">العودة لتفاصيل الطلب</a>
    <button type="button" class="primary" onclick="window.print()">طباعة النموذج</button>
</div>

<main class="sheet">
    <header class="header">
        <div class="brand">
            <h1>{{ $appName }}</h1>
            <p>نموذج طلب عميل / إيصال تجهيز الطلب</p>
            @if(!empty($supportPhone))
                <p>هاتف الدعم: <span dir="ltr">{{ $supportPhone }}</span></p>
            @endif
        </div>
        <div class="meta">
            <div class="order-no">{{ $order->order_number }}</div>
            <div class="muted" style="color:var(--muted);margin-top:4px;">
                تاريخ الإنشاء: {{ $order->created_at?->format('Y-m-d H:i') }}
            </div>
            @if($order->confirmed_at)
                <div class="muted" style="color:var(--muted);">
                    تاريخ التأكيد: {{ $order->confirmed_at->format('Y-m-d H:i') }}
                </div>
            @endif
            <div style="margin-top:8px;">
                <span class="badge">{{ $order->status->label() }}</span>
            </div>
        </div>
    </header>

    <section class="grid">
        <div class="box">
            <h3>بيانات العميل</h3>
            <div class="value">{{ $order->customer_name_snapshot }}</div>
            <div class="muted" dir="ltr">{{ $order->customer_phone_snapshot }}</div>
            @if($order->customer?->email)
                <div class="muted">{{ $order->customer->email }}</div>
            @endif
        </div>

        <div class="box">
            <h3>عنوان التوصيل</h3>
            @if($order->deliveryAddress)
                <div class="value">
                    {{ $order->deliveryAddress->contact_name }}
                    <span class="muted" dir="ltr">({{ $order->deliveryAddress->contact_phone }})</span>
                </div>
                <div class="muted">{{ $order->deliveryAddress->formatted_address_line }}</div>
                @if($order->deliveryAddress->notes)
                    <div class="muted">ملاحظات العنوان: {{ $order->deliveryAddress->notes }}</div>
                @endif
            @else
                <div class="muted">لا يوجد عنوان توصيل مسجّل</div>
            @endif
        </div>

        <div class="box">
            <h3>طريقة التوصيل والدفع</h3>
            <div class="value">{{ $order->shipping_method_name_ar ?: 'توصيل قياسي' }}</div>
            <div class="muted">طريقة الدفع: {{ $order->paymentMethodLabel() }}</div>
            <div class="muted">حالة الدفع: {{ $order->paymentStatusOrDefault()->label() }}</div>
            <div class="muted">المستودع: {{ $order->warehouse?->name_ar ?? '—' }}</div>
        </div>

        <div class="box">
            <h3>تغليف الهدية</h3>
            @if($order->hasGiftWrapping())
                <div class="value">نعم — تغليف هدية فاخر (مجاناً)</div>
                @if($order->hasGiftMessage())
                    <div class="muted">رسالة الإهداء: {{ $order->gift_message_snapshot }}</div>
                @endif
            @else
                <div class="value">لا</div>
            @endif
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th class="center" style="width:40px;">#</th>
                <th>الصنف</th>
                <th class="center" style="width:70px;">الوحدة</th>
                <th class="center" style="width:80px;">الكمية</th>
                <th class="num" style="width:110px;">سعر الوحدة</th>
                <th class="num" style="width:100px;">الخصم</th>
                <th class="num" style="width:120px;">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name_snapshot }}</strong>
                        @if($item->variant_name_snapshot)
                            <div class="muted" style="color:var(--muted);font-size:12px;">{{ $item->variant_name_snapshot }}</div>
                        @endif
                        <div class="muted" style="color:var(--muted);font-size:11px;">SKU: {{ $item->sku_snapshot }}</div>
                    </td>
                    <td class="center">{{ $item->unit_name_snapshot ?: '—' }}</td>
                    <td class="center">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="num">{{ $fmt($item->unit_price) }}</td>
                    <td class="num">{{ (float) $item->discount_amount > 0 ? $fmt($item->discount_amount) : '—' }}</td>
                    <td class="num"><strong>{{ $fmt($item->line_total) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>المجموع الفرعي</td>
            <td class="num">{{ $fmt($order->subtotal) }}</td>
        </tr>
        @if((float) $order->discount_amount > 0)
            <tr>
                <td>إجمالي الخصم</td>
                <td class="num">- {{ $fmt($order->discount_amount) }}</td>
            </tr>
        @endif
        <tr>
            <td>رسوم التوصيل</td>
            <td class="num">{{ (float) $order->delivery_fee > 0 ? $fmt($order->delivery_fee) : 'مجاناً' }}</td>
        </tr>
        <tr class="total">
            <td>الإجمالي المستحق</td>
            <td class="num">{{ $fmt($order->total) }}</td>
        </tr>
    </table>

    @if($order->notes)
        <div class="notes">
            <strong>ملاحظات الطلب:</strong>
            <div>{{ $order->notes }}</div>
        </div>
    @endif

    <section class="signatures">
        <div class="sig">توقيع المستلم</div>
        <div class="sig">توقيع المندوب</div>
        <div class="sig">ختم / مسؤول التجهيز</div>
    </section>

    <footer class="footer">
        <div>طُبع بواسطة: {{ auth()->user()?->name ?? 'النظام' }}</div>
        <div>{{ now()->format('Y-m-d H:i') }}</div>
    </footer>
</main>
</body>
</html>
