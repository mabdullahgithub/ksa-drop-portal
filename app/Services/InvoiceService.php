<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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

        $pdf = Pdf::loadView('invoices.shipping', [
            'invoice' => $invoice,
            'order' => $order,
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
