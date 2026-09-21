<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>KSA Express waybills</title>
    {{-- Many KSA Express labels in one PDF, one 4x6 page per shipment.
         Same layout as ksadrop-express-label.blade.php. --}}
    @include('invoices.partials.ksadrop-express-label-styles')
</head>
<body>
    @foreach($labels as $label)
        <div @unless($loop->last) style="page-break-after: always;" @endunless>
            @include('invoices.partials.ksadrop-express-label-body', $label)
        </div>
    @endforeach
</body>
</html>
