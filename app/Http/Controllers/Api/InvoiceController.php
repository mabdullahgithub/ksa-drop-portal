<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\InvoiceService;
use App\Services\Shipping\Drivers\KsaDropExpressDriver;
use App\Services\Shipping\Enums\ShipmentStatus;
use Illuminate\Http\Request;
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
     * Generate the KSA Express waybills of many orders as one PDF download (admin).
     *
     * All or nothing: every selected order must have a live shipment booked
     * with KSA Express, otherwise nothing is generated and the 422 response
     * lists each order that blocks the run.
     */
    public function bulkKsaExpressWaybills(Request $request)
    {
        $validated = $request->validate([
            'order_ids'   => 'required|array|min:1|max:200',
            'order_ids.*' => 'integer',
        ]);

        $orderIds = array_values(array_unique($validated['order_ids']));

        // A cancelled or failed booking has no usable label; the order's
        // current shipment is its latest one that is still live.
        $orders = Order::whereIn('id', $orderIds)
            ->with(['items', 'client', 'shipments' => fn ($q) => $q
                ->whereNotIn('status', [ShipmentStatus::CANCELLED->value, ShipmentStatus::FAILED->value])
                ->latest('id')])
            ->get()
            ->keyBy('id');

        $shipments = [];
        $errors = [];

        foreach ($orderIds as $id) {
            $order = $orders->get($id);

            if (! $order) {
                $errors[] = ['order_id' => $id, 'order_number' => null, 'error' => 'Order not found.'];
                continue;
            }

            $shipment = $order->shipments->first();

            if (! $shipment) {
                $errors[] = ['order_id' => $id, 'order_number' => $order->order_number, 'error' => 'No shipment has been created for this order.'];
            } elseif ($shipment->courier !== KsaDropExpressDriver::KEY) {
                $errors[] = ['order_id' => $id, 'order_number' => $order->order_number, 'error' => 'Shipment is not assigned to KSA Express.'];
            } elseif (! $shipment->tracking_number) {
                $errors[] = ['order_id' => $id, 'order_number' => $order->order_number, 'error' => 'KSA Express shipment has no tracking number yet.'];
            } else {
                $shipment->setRelation('order', $order);
                $shipments[] = $shipment;
            }
        }

        if ($errors) {
            // e.g. "2 order(s): No shipment has been created for this order. 1 order(s): Shipment is not assigned to KSA Express."
            $summary = collect($errors)->countBy('error')
                ->map(fn ($count, $error) => "{$count} order(s): {$error}")
                ->implode(' ');

            $message = "Waybills were not generated — every selected order needs a KSA Express shipment. {$summary}";

            return response()->json(['message' => $message, 'errors' => $errors], 422);
        }

        $pdf = $this->invoices->bulkKsaExpressLabels($shipments);
        $filename = 'ksa-express-waybills-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Delete a generated invoice / waybill and its stored PDF (admin).
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->file_path) {
            Storage::disk('local')->delete($invoice->file_path);
        }

        $invoice->delete();

        return response()->json(['message' => 'Waybill deleted.']);
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
