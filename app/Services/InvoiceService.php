<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Warehouse;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorSVG;

class InvoiceService
{
    /** KSA standard VAT rate. */
    public const VAT_RATE = 0.15;

    /**
     * Seller (KSA Drop) details shown on every invoice.
     */
    protected function seller(): array
    {
        $warehouse = Warehouse::getDefault();

        return [
            'name' => config('app.name', 'KSA Drop'),
            'name_ar' => 'كيه إس إيه دروب',
            'logo' => $this->logoDataUri(),
            'vat_number' => config('invoice.vat_number', '300000000000003'),
            'cr_number' => config('invoice.cr_number', ''),
            'address' => $warehouse?->address ?? 'Riyadh, Saudi Arabia',
            'city' => $warehouse?->city ?? 'Riyadh',
            'phone' => $warehouse?->phone ?? '',
            'email' => config('invoice.email', 'support@ksadrop.com'),
        ];
    }

    /**
     * Return the company logo as a base64 data URI for embedding in PDFs,
     * or null if the asset is missing.
     */
    protected function logoDataUri(): ?string
    {
        $path = public_path('ksa-al-logo-files/Logo/KSA Drop Logo PNG-02.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
    }

    /**
     * Generate (or regenerate) the shipping invoice / waybill for a shipment.
     */
    public function generateShippingInvoice(Shipment $shipment): Invoice
    {
        $invoice = $this->issueShippingInvoice($shipment);

        // Our own courier has no carrier-issued label, so its waybill is a
        // 4x6 thermal label carrying a scannable barcode of the tracking number.
        $pdf = $this->isKsaExpressLabel($shipment)
            ? Pdf::loadView('invoices.ksadrop-express-label', $this->ksaExpressLabelData($shipment, $invoice))
                ->setPaper([0, 0, 288, 432])
            : Pdf::loadView('invoices.shipping', [
                'invoice' => $invoice,
                'order' => $shipment->order,
                'shipment' => $shipment,
                'seller' => $this->seller(),
            ])->setPaper('a4', 'portrait');

        $path = "invoices/shipping/{$invoice->invoice_number}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        $invoice->file_path = $path;
        $invoice->save();

        return $invoice;
    }

    /**
     * Render the KSA Express labels of many shipments into one PDF, one 4x6
     * page each, and return its bytes. Each shipment gets its shipping
     * invoice record (so the label carries the same invoice number as its
     * single waybill); the per-shipment PDF is left to be rendered on demand.
     *
     * @param  iterable<Shipment>  $shipments  KSA Express shipments with a tracking number
     */
    public function bulkKsaExpressLabels(iterable $shipments): string
    {
        $labels = [];

        foreach ($shipments as $shipment) {
            $labels[] = $this->ksaExpressLabelData($shipment, $this->issueShippingInvoice($shipment));
        }

        return Pdf::loadView('invoices.ksadrop-express-labels-bulk', ['labels' => $labels])
            ->setPaper([0, 0, 288, 432])
            ->output();
    }

    /**
     * Create or refresh the shipping invoice record for a shipment.
     */
    protected function issueShippingInvoice(Shipment $shipment): Invoice
    {
        $shipment->loadMissing('order.items', 'order.client');
        $order = $shipment->order;

        $invoice = Invoice::firstOrNew([
            'order_id' => $order->id,
            'shipment_id' => $shipment->id,
            'type' => 'shipping',
        ]);

        if (! $invoice->invoice_number) {
            $invoice->invoice_number = Invoice::generateInvoiceNumber('shipping');
        }

        $invoice->status = 'issued';
        $invoice->issued_at = now();
        $invoice->subtotal = $order->subtotal;
        $invoice->shipping_amount = $order->shipping_cost;
        $invoice->tax_amount = $order->taxes;
        $invoice->total = $order->total;
        $invoice->save();

        return $invoice;
    }

    protected function isKsaExpressLabel(Shipment $shipment): bool
    {
        return $shipment->courier === KsaDropExpressDriver::KEY && $shipment->tracking_number;
    }

    /**
     * View data for one KSA Express label (invoices.partials.ksadrop-express-label-body).
     */
    protected function ksaExpressLabelData(Shipment $shipment, Invoice $invoice): array
    {
        $shipment->loadMissing('order.items', 'order.client');
        $order = $shipment->order;

        return [
            'invoice' => $invoice,
            'order' => $order,
            'shipment' => $shipment,
            'seller' => $this->seller(),
            'barcode' => $this->barcodeDataUri($shipment->tracking_number),
            'orderBarcode' => $this->barcodeDataUri((string) $order->order_number, 1, 30),
            'qr' => $this->qrDataUri(rtrim(config('app.url'), '/') . '/track?q=' . urlencode($shipment->tracking_number)),
        ];
    }

    /**
     * Code 128 barcode as an SVG data URI for embedding in PDFs.
     */
    protected function barcodeDataUri(string $value, int $widthFactor = 2, int $height = 60): string
    {
        $svg = (new BarcodeGeneratorSVG())->getBarcode($value, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * QR code as an SVG data URI for embedding in PDFs.
     */
    protected function qrDataUri(string $value): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 0), new SvgImageBackEnd()));

        return 'data:image/svg+xml;base64,' . base64_encode($writer->writeString($value));
    }

    /**
     * Return the absolute filesystem path to an invoice PDF, generating it if missing.
     */
    public function getInvoicePdfPath(Invoice $invoice): string
    {
        if (! $invoice->file_path || ! Storage::disk('local')->exists($invoice->file_path)) {
            if ($invoice->type === 'shipping' && $invoice->shipment) {
                $invoice = $this->generateShippingInvoice($invoice->shipment);
            }
        }

        return Storage::disk('local')->path($invoice->file_path);
    }
}
