<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Waybill {{ $invoice->invoice_number }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111827; padding-bottom: 8px; }
        .brand h1 { margin: 0; font-size: 16px; color: #111827; }
        .brand p { margin: 1px 0; color: #6b7280; font-size: 10px; }
        .brand .logo { height: 36px; width: auto; margin-bottom: 4px; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 15px; color: #111827; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ar { color: #6b7280; font-size: 12px; }
        .doc-title p { margin: 2px 0; color: #6b7280; font-size: 10px; }
        .tracking { margin-top: 10px; padding: 8px 12px; border: 2px dashed #111827; border-radius: 4px; text-align: center; }
        .tracking .label { color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .tracking .code { font-size: 22px; font-weight: bold; letter-spacing: 2px; margin-top: 2px; }
        .tracking .sorting { margin-top: 3px; font-size: 11px; color: #374151; }
        .parties { display: flex; justify-content: space-between; margin-top: 10px; }
        .party { width: 48.5%; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px; }
        .party h3 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
        .party p { margin: 1px 0; font-size: 11px; }
        .party .name { font-weight: bold; font-size: 12px; }
        .meta { display: flex; justify-content: space-between; margin-top: 10px; font-size: 11px; border: 1px solid #e5e7eb; border-radius: 4px; }
        .meta div { text-align: center; flex: 1; padding: 6px 4px; border-right: 1px solid #e5e7eb; }
        .meta div:last-child { border-right: none; }
        .meta .label { color: #6b7280; font-size: 9px; text-transform: uppercase; }
        .meta .value { font-weight: bold; font-size: 13px; margin-top: 1px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #f3f4f6; padding: 5px 8px; text-align: left; font-size: 10px; color: #374151; }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { padding: 4px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
        .footer { margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 5px; color: #9ca3af; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if($seller['logo'])
                <img class="logo" src="{{ $seller['logo'] }}" alt="{{ $seller['name'] }}">
            @else
                <h1>{{ $seller['name'] }}</h1>
            @endif
            <p>Shipped via J&amp;T Express</p>
            <p>{{ $seller['address'] }}, {{ $seller['city'] }}</p>
        </div>
        <div class="doc-title">
            <h2>Waybill / بوليصة الشحن</h2>
            <p>{{ $invoice->invoice_number }}</p>
            <p>Order #{{ $order->order_number }}</p>
        </div>
    </div>

    <div class="tracking">
        <div class="label">Tracking Number / رقم التتبع</div>
        <div class="code">{{ $shipment->tracking_number ?? 'PENDING' }}</div>
        @if($shipment->sorting_code)
            <div class="sorting">Sorting Code: {{ $shipment->sorting_code }}</div>
        @endif
    </div>

    <div class="parties">
        <div class="party">
            <h3>From / المرسل</h3>
            <p class="name">{{ $seller['name'] }}</p>
            <p>{{ $seller['address'] }}</p>
            <p>{{ $seller['city'] }}</p>
            @if($seller['phone'])<p>{{ $seller['phone'] }}</p>@endif
        </div>
        <div class="party">
            <h3>To / المستلم</h3>
            <p class="name">{{ $order->shipping_name ?? $order->customer_name ?? 'N/A' }}</p>
            @if($order->shipping_address1)<p>{{ $order->shipping_address1 }}</p>@endif
            <p>{{ trim($order->shipping_city . ' ' . $order->shipping_province) }}</p>
            <p>{{ trim($order->shipping_country . ' ' . $order->shipping_zip) }}</p>
            @if($order->shipping_phone)<p>{{ $order->shipping_phone }}</p>@endif
        </div>
    </div>

    <div class="meta">
        <div>
            <div class="label">Weight</div>
            <div class="value">{{ number_format((float) $shipment->weight, 2) }} kg</div>
        </div>
        <div>
            <div class="label">Service</div>
            <div class="value">{{ $shipment->service_type === '01' ? 'Express' : 'Standard' }}</div>
        </div>
        <div>
            <div class="label">Items</div>
            <div class="value">{{ $order->items->sum('lineitem_quantity') }}</div>
        </div>
        @if($order->payment_method && str_contains(strtolower($order->payment_method), 'cod'))
        <div>
            <div class="label">COD Amount</div>
            <div class="value">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</div>
        </div>
        @endif
    </div>

    @php $items = $order->items; $maxRows = 12; $extra = max(0, $items->count() - $maxRows); @endphp
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item / الصنف</th>
                <th class="num">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items->take($maxRows) as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $item->lineitem_name }}
                        @if($item->variant_name) — <span style="color:#9ca3af;">{{ $item->variant_name }}</span>@endif
                    </td>
                    <td class="num">{{ $item->lineitem_quantity }}</td>
                </tr>
            @endforeach
            @if($extra > 0)
                <tr><td colspan="3" style="color:#6b7280;font-style:italic;padding:3px 8px;">+ {{ $extra }} more item(s) — see order #{{ $order->order_number }}</td></tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Generated {{ now()->format('Y-m-d H:i') }} — {{ $seller['name'] }} fulfillment / تم الإنشاء إلكترونياً
    </div>
</body>
</html>
