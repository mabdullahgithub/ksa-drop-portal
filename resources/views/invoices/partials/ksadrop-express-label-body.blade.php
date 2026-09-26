{{-- One 4x6 KSA Express label. Shared by the single waybill and the bulk PDF;
     expects $order, $shipment, $invoice, $seller, $barcode, $orderBarcode, $qr. --}}
    @use('App\Services\Shipping\WaybillTextFit', 'Fit')
    @php
        $isCod = $order->payment_method && str_contains(strtolower($order->payment_method), 'cod');
        $pieces = (int) $order->items->sum('lineitem_quantity');
        $shippedAt = $shipment->shipped_at ?? now();

        // Receiver as confirmed in the create dialog (KsaDropExpressDriver::bookingRecord),
        // falling back to the order's shipping address.
        $r = $shipment->api_response['receiver'] ?? [];
        $to = [
            'name'     => ($r['name'] ?? null) ?: ($order->shipping_name ?? $order->customer_name ?? 'N/A'),
            'phone'    => ($r['phone'] ?? null) ?: ($order->shipping_phone ?? $order->customer_phone),
            'address'  => ($r['address'] ?? null) ?: collect([$order->shipping_address1, $order->shipping_address2])->filter()->implode(', '),
            'area'     => $r['area'] ?? null,
            'city'     => ($r['city'] ?? null) ?: $order->shipping_city,
            'province' => ($r['province'] ?? null) ?: $order->shipping_province,
            'postCode' => ($r['postCode'] ?? null) ?: $order->shipping_zip,
        ];

        $s = fn ($v) => trim((string) $v);
        $destCity = $s($to['city'] ?: ($to['province'] ?: '—'));
        $destArea = $to['province'] && $to['province'] !== $to['city'] ? $s($to['province']) : '';
        $codText = $isCod ? $order->currency . ' ' . number_format((float) $order->total, 2) : 'PREPAID';
        $toName = $s($to['name']);
        $toPhone = $s($to['phone'] ?: '—');
        $toAddress = $s(collect([$to['address'], $to['area']])->filter()->implode(', ') ?: '—');
        $toRegion = $s(collect([$to['city'], $to['province'], $to['postCode'], $order->shipping_country])->filter()->unique()->implode(', '));
        $shipperName = $s($order->client?->company_name ?: $seller['name']);
        $shipperPhone = $s($seller['phone'] ?: '—');
        $shipperAddress = $s($seller['address'] . ', ' . $seller['city']);
        $reference = $s($order->order_number);
        $remark = $s($shipment->api_response['remark'] ?? '');

        // Box sizes in px (dompdf: 1px = 0.75pt). Inner width of a
        // padded row is ~347px on a 4in page with 3mm margins.
        $W = 341; // minus .fit's 4px right safety margin
        $destBoxH = 34;

        $destCitySize = Fit::size($destCity, 204, $destArea !== '' ? 22 : $destBoxH, [20, 16, 13, 11, 9, 8, 7, 6], Fit::BOLD_UPPER);
        $destAreaSize = $destArea !== '' ? Fit::size($destArea, 204, $destBoxH - $destCitySize * Fit::LINE, [8, 7, 6, 5.5, 5, 4.5], Fit::BOLD_UPPER) : 0;
        $codSize = Fit::size($codText, 128, 24, [17, 14, 12, 10, 9, 8], Fit::BOLD);

        $partyNameW = 200; $partyPhoneW = 130;
        $toNameSize = Fit::size($toName, $partyNameW, 17, [12, 11, 10, 9, 8, 7, 6, 5.5, 5], Fit::BOLD);
        $toPhoneSize = Fit::size($toPhone, $partyPhoneW, 17, [12, 11, 10, 9, 8, 7, 6], Fit::BOLD);
        // Address and city/region share one box so the region follows the address.
        $toAddrSize = Fit::size($toAddress . "\n" . $toRegion, $W, 51, [9.5, 8.5, 7.5, 7, 6.5, 6, 5.5, 5, 4.5]);

        $shipperNameSize = Fit::size($shipperName, $partyNameW, 17, [12, 11, 10, 9, 8, 7, 6, 5.5, 5], Fit::BOLD);
        $shipperPhoneSize = Fit::size($shipperPhone, $partyPhoneW, 17, [12, 11, 10, 9, 8, 7, 6], Fit::BOLD);
        $shipperAddrSize = Fit::size($shipperAddress, $W, 14, [9.5, 8.5, 7.5, 7, 6.5, 6, 5.5, 5, 4.5]);

        $refSize = Fit::size($reference, 108, 16, [11, 10, 9, 8, 7, 6, 5.5, 5], Fit::BOLD);
        $refLineSize = Fit::size('REF ' . $reference, 208, 9, [6.5, 6, 5.5, 5, 4.5]);

        // Contents + note: the note (max 200 chars) is always shown whole;
        // the item list takes what's left of the box.
        $contentsBoxH = 43;
        $noteText = $remark !== '' ? 'Note: ' . $remark : '';
        $noteSize = $noteText !== '' ? Fit::size($noteText, $W, 14, [8.5, 7.5, 6.5, 6, 5.5, 5, 4.5]) : 0;
        $noteH = $noteText !== '' ? Fit::lines($noteText, $W, $noteSize) * $noteSize * Fit::LINE : 0;
        [$contents, $contentsSize] = Fit::list(
            $order->items->map(fn ($i) => trim($i->lineitem_name . ($i->variant_name ? ' - ' . $i->variant_name : '')) . ' x' . $i->lineitem_quantity)->all() ?: ['—'],
            ', ',
            $W,
            $contentsBoxH - $noteH,
            [8.5, 7.5, 6.5, 6, 5.5, 5],
            '+%d more item(s) — see order ' . $reference,
        );
    @endphp

    {{-- Header: courier, service, payment type --}}
    <table class="grid">
        <tr>
            <td class="pad" style="width: 52%;">
                <div class="brand">KSA EXPRESS</div>
                <div class="brand-sub">DOMESTIC WAYBILL</div>
            </td>
            <td class="pad center" style="width: 24%;">
                <div class="k">Service</div>
                <div class="svc">{{ $shipment->service_type === '01' ? 'EXPRESS' : 'STANDARD' }}</div>
                <div style="font-size: 7px;">{{ $shippedAt->format('d M Y') }}</div>
            </td>
            <td class="badge" style="width: 24%;">{{ $isCod ? 'COD' : 'PPD' }}</td>
        </tr>
    </table>

    {{-- Main AWB barcode --}}
    <table class="grid">
        <tr>
            <td class="barcode center" style="padding: 5px 4px 3px; border-top: none;">
                <img src="{{ $barcode }}" alt="{{ $shipment->tracking_number }}">
                <div class="awb">{{ $shipment->tracking_number }}</div>
            </td>
        </tr>
    </table>

    {{-- Destination (sort) + payment --}}
    <table class="grid">
        <tr>
            <td class="pad" style="width: 60%; border-top: none;">
                <div class="k">Destination</div>
                <div class="fit" style="height: {{ $destBoxH }}px;">
                    <div class="b up" style="font-size: {{ $destCitySize }}px; line-height: {{ $destCitySize * 1.3 }}px;">{{ Fit::html($destCity, 204, $destCitySize, Fit::BOLD_UPPER) }}</div>
                    @if($destArea !== '')<div class="b up" style="font-size: {{ $destAreaSize }}px; line-height: {{ $destAreaSize * 1.3 }}px;">{{ Fit::html($destArea, 204, $destAreaSize, Fit::BOLD_UPPER) }}</div>@endif
                </div>
            </td>
            <td class="pad center" style="width: 40%; border-top: none;">
                <div class="k">{{ $isCod ? 'Cash on delivery' : 'Payment' }}</div>
                <div class="fit b" style="height: 24px; font-size: {{ $codSize }}px; line-height: {{ $codSize * 1.3 }}px;">{{ $codText }}</div>
                @unless($isCod)<div style="font-size: 7px;">Collect nothing</div>@endunless
            </td>
        </tr>
    </table>

    {{-- Consignee --}}
    <table class="grid">
        <tr>
            <td class="pad" style="border-top: none;">
                <table class="plain">
                    <tr>
                        <td style="width: 60%;"><div class="k">Consignee (To)</div></td>
                        <td style="width: 40%;" class="right"><div class="k">Phone</div></td>
                    </tr>
                    <tr>
                        <td><div class="fit b" style="height: 17px; font-size: {{ $toNameSize }}px; line-height: {{ $toNameSize * 1.3 }}px;">{{ Fit::html($toName, $partyNameW, $toNameSize, Fit::BOLD) }}</div></td>
                        <td class="right"><div class="fit b" style="height: 17px; font-size: {{ $toPhoneSize }}px; line-height: {{ $toPhoneSize * 1.3 }}px;">{{ $toPhone }}</div></td>
                    </tr>
                </table>
                <div class="fit" style="height: 51px; font-size: {{ $toAddrSize }}px; line-height: {{ $toAddrSize * 1.3 }}px;">
                    <div>{{ Fit::html($toAddress, $W, $toAddrSize) }}</div>
                    <div>{{ Fit::html($toRegion, $W, $toAddrSize) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Shipper --}}
    <table class="grid">
        <tr>
            <td class="pad" style="border-top: none;">
                <table class="plain">
                    <tr>
                        <td style="width: 60%;"><div class="k">Shipper (From)</div></td>
                        <td style="width: 40%;" class="right"><div class="k">Phone</div></td>
                    </tr>
                    <tr>
                        <td><div class="fit b" style="height: 17px; font-size: {{ $shipperNameSize }}px; line-height: {{ $shipperNameSize * 1.3 }}px;">{{ Fit::html($shipperName, $partyNameW, $shipperNameSize, Fit::BOLD) }}</div></td>
                        <td class="right"><div class="fit b" style="height: 17px; font-size: {{ $shipperPhoneSize }}px; line-height: {{ $shipperPhoneSize * 1.3 }}px;">{{ $shipperPhone }}</div></td>
                    </tr>
                </table>
                <div class="fit" style="height: 14px; font-size: {{ $shipperAddrSize }}px; line-height: {{ $shipperAddrSize * 1.3 }}px;">{{ Fit::html($shipperAddress, $W, $shipperAddrSize) }}</div>
            </td>
        </tr>
    </table>

    {{-- Shipment details --}}
    <table class="grid">
        <tr>
            <td class="pad center" style="width: 34%; border-top: none;">
                <div class="k">Reference</div>
                <div class="fit b" style="height: 16px; font-size: {{ $refSize }}px; line-height: {{ $refSize * 1.3 }}px;">{{ Fit::html($reference, 108, $refSize, Fit::BOLD) }}</div>
            </td>
            <td class="pad center" style="width: 16%; border-top: none;">
                <div class="k">Pieces</div>
                <div class="b" style="font-size: 11px;">1</div>
            </td>
            <td class="pad center" style="width: 16%; border-top: none;">
                <div class="k">Items</div>
                <div class="b" style="font-size: 11px;">{{ $pieces }}</div>
            </td>
            <td class="pad center" style="width: 34%; border-top: none;">
                <div class="k">Weight</div>
                <div class="b" style="font-size: 11px;">{{ number_format((float) $shipment->weight, 2) }} KG</div>
            </td>
        </tr>
    </table>

    {{-- Contents --}}
    <table class="grid">
        <tr>
            <td class="pad" style="border-top: none;">
                <div class="k">Description of contents</div>
                <div class="fit" style="height: {{ $contentsBoxH }}px;">
                    <div style="font-size: {{ $contentsSize }}px; line-height: {{ $contentsSize * 1.3 }}px;">{{ Fit::html($contents, $W, $contentsSize) }}</div>
                    @if($noteText !== '')<div style="font-size: {{ $noteSize }}px; line-height: {{ $noteSize * 1.3 }}px;">{!! \Illuminate\Support\Str::replaceFirst('Note:', '<b>Note:</b>', Fit::html($noteText, $W, $noteSize)) !!}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Proof of delivery + order barcode + QR --}}
    <table class="grid">
        <tr>
            <td class="pad" style="width: 62%; border-top: none;">
                <div class="k">Received in good condition</div>
                <table class="plain" style="margin-top: 3px;">
                    <tr>
                        <td style="width: 26%;" class="k">Name</td>
                        <td><div class="sign-line"></div></td>
                    </tr>
                    <tr>
                        <td class="k" style="padding-top: 3px;">Signature</td>
                        <td><div class="sign-line"></div></td>
                    </tr>
                    <tr>
                        <td class="k" style="padding-top: 3px;">Date</td>
                        <td><div class="sign-line"></div></td>
                    </tr>
                </table>
                <div class="order-barcode center" style="margin-top: 5px;">
                    <img src="{{ $orderBarcode }}" alt="{{ $reference }}">
                    <div class="fit" style="height: 9px; font-size: {{ $refLineSize }}px; line-height: {{ $refLineSize * 1.3 }}px; letter-spacing: 0.5px;">{{ Fit::html('REF ' . $reference, 208, $refLineSize) }}</div>
                </div>
            </td>
            <td class="pad center" style="width: 38%; border-top: none; vertical-align: middle;">
                <div class="qr"><img src="{{ $qr }}" alt="Track"></div>
                <div class="k" style="margin-top: 2px;">Scan to track</div>
                <div style="font-size: 6px; margin-top: 1px;">{{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}/track</div>
                <div style="font-size: 5.5px; margin-top: 3px;">{{ $invoice->invoice_number }} &middot; {{ now()->format('d M Y H:i') }}</div>
            </td>
        </tr>
    </table>
