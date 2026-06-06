<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoices) {}

    /**
     * List invoices for an order (admin).
     */
    public function indexForOrder(Order $order)
    {
        return response()->json([
            'invoices' => $order->invoices()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Generate the shipping waybill for a shipment (admin).
     */
    public function generateShipping(Shipment $shipment)
    {
        $invoice = $this->invoices->generateShippingInvoice($shipment);

        return response()->json([
            'message' => 'Shipping invoice generated.',
            'invoice' => $invoice,
        ], 201);
    }

    /**
     * Stream an invoice PDF inline for preview (admin).
     */
    public function preview(Invoice $invoice)
    {
        return $this->streamPdf($invoice, inline: true);
    }

    /**
     * Download an invoice PDF (admin).
     */
    public function download(Invoice $invoice)
    {
        return $this->streamPdf($invoice, inline: false);
    }

    /**
     * Portal: preview an invoice belonging to the authenticated client's order.
     */
    public function portalPreview(Invoice $invoice)
    {
        $this->authorizePortalAccess($invoice);

        return $this->streamPdf($invoice, inline: true);
    }

    /**
     * Portal: download an invoice belonging to the authenticated client's order.
     */
    public function portalDownload(Invoice $invoice)
    {
        $this->authorizePortalAccess($invoice);

        return $this->streamPdf($invoice, inline: false);
    }

    protected function authorizePortalAccess(Invoice $invoice): void
    {
        $client = auth()->user()?->client;

        abort_if(! $client || $invoice->order->client_id !== $client->id, 403);
    }

    protected function streamPdf(Invoice $invoice, bool $inline)
    {
        $invoice->loadMissing('order', 'shipment');

        $path = $this->invoices->getInvoicePdfPath($invoice);

        if (! file_exists($path)) {
            abort(404, 'Invoice file not found.');
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $invoice->invoice_number . '.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
