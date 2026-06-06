<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Tax Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 32px;
        }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 16px; }
        .brand h1 { margin: 0; font-size: 22px; color: #111827; }
        .brand .ar { color: #6b7280; font-size: 13px; }
        .brand p { margin: 2px 0; color: #6b7280; font-size: 11px; }
        .brand .logo { height: 48px; width: auto; margin-bottom: 8px; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 18px; color: #111827; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ar { color: #6b7280; font-size: 13px; }
        .doc-title table { font-size: 11px; margin-top: 8px; }
        .doc-title td { padding: 1px 0; }
        .doc-title td:first-child { color: #6b7280; padding-right: 12px; }
        .parties { display: flex; justify-content: space-between; margin-top: 24px; }
        .party { width: 48%; }
        .party h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
        .party p { margin: 2px 0; }
        .party .name { font-weight: bold; font-size: 13px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 28px; }
        table.items th { background: #111827; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .totals { margin-top: 18px; width: 100%; }
        .totals table { margin-left: auto; width: 300px; border-collapse: collapse; }
        .totals td { padding: 5px 10px; }
        .totals td:first-child { color: #6b7280; }
        .totals td:last-child { text-align: right; }
        .totals tr.grand td { border-top: 2px solid #111827; font-weight: bold; font-size: 14px; padding-top: 8px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 10px; text-align: center; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; background: #ecfdf5; color: #047857; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if($seller['logo'])
                <img class="logo" src="{{ $seller['logo'] }}" alt="{{ $seller['name'] }}">
            @else
                <h1>{{ $seller['name'] }}</h1>
                <div class="ar">{{ $seller['name_ar'] }}</div>
            @endif
            <p>{{ $seller['address'] }}, {{ $seller['city'] }}</p>
            @if($seller['phone'])<p>{{ $seller['phone'] }}</p>@endif
            <p>VAT No. / الرقم الضريبي: {{ $seller['vat_number'] }}</p>
        </div>
        <div class="doc-title">
            <h2>Tax Invoice</h2>
            <div class="ar">فاتورة ضريبية</div>
            <table>
                <tr><td>Invoice #</td><td>{{ $invoice->invoice_number }}</td></tr>
                <tr><td>Order #</td><td>{{ $order->order_number }}</td></tr>
                <tr><td>Date</td><td>{{ optional($invoice->issued_at)->format('Y-m-d') }}</td></tr>
                <tr><td>Status</td><td><span class="badge">{{ ucfirst($invoice->status) }}</span></td></tr>
            </table>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <h3>Billed To / فاتورة إلى</h3>
            <p class="name">{{ $order->client->company_name ?? $order->customer_name ?? 'N/A' }}</p>
            @if($order->client)
                @if($order->client->tax_id)<p>VAT: {{ $order->client->tax_id }}</p>@endif
                @if($order->client->address)<p>{{ $order->client->address }}</p>@endif
                <p>{{ $order->client->city }} {{ $order->client->country }}</p>
                @if($order->client->phone)<p>{{ $order->client->phone }}</p>@endif
            @else
                @if($order->customer_phone)<p>{{ $order->customer_phone }}</p>@endif
                @if($order->customer_email)<p>{{ $order->customer_email }}</p>@endif
            @endif
        </div>
        <div class="party">
            <h3>Ship To / الشحن إلى</h3>
            <p class="name">{{ $order->shipping_name ?? $order->customer_name ?? 'N/A' }}</p>
            @if($order->shipping_address1)<p>{{ $order->shipping_address1 }}</p>@endif
            <p>{{ $order->shipping_city }} {{ $order->shipping_province }}</p>
            <p>{{ $order->shipping_country }} {{ $order->shipping_zip }}</p>
            @if($order->shipping_phone)<p>{{ $order->shipping_phone }}</p>@endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Description / الوصف</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->lineitem_name }}
                        @if($item->variant_name)<br><span style="color:#9ca3af;font-size:10px;">{{ $item->variant_name }}</span>@endif
                    </td>
                    <td class="num">{{ $item->lineitem_quantity }}</td>
                    <td class="num">{{ number_format((float) $item->lineitem_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->lineitem_price * $item->lineitem_quantity, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Subtotal / المجموع الفرعي</td><td>{{ $order->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            @if((float) $invoice->shipping_amount > 0)
            <tr><td>Shipping / الشحن</td><td>{{ $order->currency }} {{ number_format((float) $invoice->shipping_amount, 2) }}</td></tr>
            @endif
            <tr><td>VAT (15%) / ضريبة القيمة المضافة</td><td>{{ $order->currency }} {{ number_format((float) $invoice->tax_amount, 2) }}</td></tr>
            <tr class="grand"><td>Total / الإجمالي</td><td>{{ $order->currency }} {{ number_format((float) $invoice->total, 2) }}</td></tr>
        </table>
    </div>

    @if($order->payment_method)
        <p style="margin-top:24px;color:#6b7280;">Payment Method: {{ $order->payment_method }}@if($order->payment_reference) — Ref: {{ $order->payment_reference }}@endif</p>
    @endif

    <div class="footer">
        This is a computer-generated tax invoice and does not require a signature. / هذه فاتورة ضريبية صادرة إلكترونياً ولا تتطلب توقيعاً.
    </div>
</body>
</html>
