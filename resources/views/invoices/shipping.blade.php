<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Waybill {{ $invoice->invoice_number }}</title>
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
        .brand p { margin: 2px 0; color: #6b7280; font-size: 11px; }
        .brand .logo { height: 48px; width: auto; margin-bottom: 8px; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 18px; color: #111827; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ar { color: #6b7280; font-size: 13px; }
        .tracking { margin-top: 20px; padding: 14px 16px; border: 2px dashed #111827; border-radius: 6px; text-align: center; }
        .tracking .label { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
        .tracking .code { font-size: 26px; font-weight: bold; letter-spacing: 2px; margin-top: 4px; }
        .tracking .sorting { margin-top: 6px; font-size: 13px; color: #374151; }
        .parties { display: flex; justify-content: space-between; margin-top: 24px; }
        .party { width: 48%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .party h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
        .party p { margin: 2px 0; }
        .party .name { font-weight: bold; font-size: 13px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.items th { background: #f3f4f6; padding: 8px 10px; text-align: left; font-size: 11px; color: #374151; }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
        .meta { display: flex; justify-content: space-between; margin-top: 20px; font-size: 12px; }
        .meta div { text-align: center; flex: 1; }
        .meta .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .meta .value { font-weight: bold; font-size: 14px; margin-top: 2px; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 12px; color: #9ca3af; font-size: 10px; text-align: center; }
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
            <h2>Waybill</h2>
            <div class="ar">بوليصة الشحن</div>
            <p style="margin-top:6px;color:#6b7280;font-size:11px;">{{ $invoice->invoice_number }}</p>
            <p style="color:#6b7280;font-size:11px;">Order #{{ $order->order_number }}</p>
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
            @php $sender = $shipment->api_response['sender'] ?? null; @endphp
            <p class="name">{{ $seller['name'] }}</p>
            <p>{{ $seller['address'] }}</p>
            <p>{{ $seller['city'] }}</p>
            @if($seller['phone'])<p>{{ $seller['phone'] }}</p>@endif
        </div>
        <div class="party">
            <h3>To / المستلم</h3>
            <p class="name">{{ $order->shipping_name ?? $order->customer_name ?? 'N/A' }}</p>
            @if($order->shipping_address1)<p>{{ $order->shipping_address1 }}</p>@endif
            <p>{{ $order->shipping_city }} {{ $order->shipping_province }}</p>
            <p>{{ $order->shipping_country }} {{ $order->shipping_zip }}</p>
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

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Item / الصنف</th>
                <th class="num">Qty</th>
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
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated {{ now()->format('Y-m-d H:i') }} — {{ $seller['name'] }} fulfillment. / تم الإنشاء إلكترونياً.
    </div>
</body>
</html>
