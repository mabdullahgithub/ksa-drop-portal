<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Waybill {{ $shipment->tracking_number }}</title>
    {{--
        4x6 thermal waybill for KSA Express (courier key ksadrop_express).

        Two hard rules:
         - Always exactly one page. Every box has a fixed height, so the
           label's total height never depends on the data.
         - Never cut text. Instead of truncating, each field is set in the
           largest font that fits its whole text in its box
           (App\Services\Shipping\WaybillTextFit). Only the item list, which
           is unbounded, can fall back to "+N more items" — whole items only.

        dompdf can't shape Arabic, so fields go through WaybillTextFit::html(),
        which joins the letters and orders them right to left (ArabicShaper).
        Tables throughout: no flexbox.
    --}}
    @include('invoices.partials.ksadrop-express-label-styles')
</head>
<body>
    @include('invoices.partials.ksadrop-express-label-body')
</body>
</html>
