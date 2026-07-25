<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportService
{
    /**
     * Column headers, in export order. Shared by the admin and portal exports
     * so the two can never drift out of sync with each other again.
     */
    private const HEADERS = [
        'Order Id', 'Name', 'Client Company', 'Client Id',
        'Customer Name', 'Customer Email', 'Customer Phone',
        'Financial Status', 'Paid at', 'Fulfillment Status', 'Fulfilled at', 'Accepts Marketing',
        'Currency', 'Subtotal', 'Shipping', 'Taxes', 'Total',
        'Discount Code', 'Discount Amount', 'Shipping Method',
        'Outstanding Balance', 'Refunded Amount', 'Cod Collected Amount', 'Cod Collected At',
        'Payment Method', 'Payment Reference',
        'Lineitem quantity', 'Lineitem name', 'Lineitem variant', 'Lineitem sku',
        'Lineitem price', 'Lineitem compare at price', 'Lineitem discount',
        'Lineitem requires shipping', 'Lineitem taxable', 'Lineitem fulfillment status',
        'Billing Name', 'Billing Street', 'Billing Address1', 'Billing Address2', 'Billing Company',
        'Billing City', 'Billing Zip', 'Billing Province', 'Billing Country', 'Billing Phone',
        'Shipping Name', 'Shipping Street', 'Shipping Address1', 'Shipping Address2', 'Shipping Company',
        'Shipping City', 'Shipping Zip', 'Shipping Province', 'Shipping Country', 'Shipping Phone',
        'Shipment Courier', 'Tracking Number', 'Shipment Status', 'Courier Status Description',
        'Shipped At', 'Delivered At', 'Return Tracking Number',
        'Notes', 'Note Attributes', 'Tags', 'Shopify Raw Tags', 'Risk Level', 'Source', 'Vendor',
        'Utm Source', 'Utm Medium', 'Utm Campaign', 'Utm Id', 'Ip Address',
        'Shopify Order Id', 'Shopify Shop Domain',
        'Cancelled at', 'Created at', 'Updated at',
    ];

    /**
     * Stream a collection of orders as a downloadable CSV.
     *
     * Callers must eager-load `items`, `client`, and `latestShipment` on
     * $orders beforehand to avoid N+1 queries.
     *
     * One row per line item (not per order): every order-level column is
     * repeated on each of its item rows, so each row is a fully self-contained
     * record and orders with multiple items no longer lose data to
     * first-item-only truncation.
     */
    public function stream(Collection $orders): StreamedResponse
    {
        $filename = 'orders_export_' . date('Y-m-d_His') . '.csv';

        return response()->stream(function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::HEADERS);

            foreach ($orders as $order) {
                $items = $order->items->isEmpty() ? [null] : $order->items;
                foreach ($items as $item) {
                    fputcsv($file, $this->buildRow($order, $item));
                }
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function buildRow($order, $item): array
    {
        $shipment = $order->latestShipment;

        return [
            $order->id,
            $order->order_number,
            $order->client?->company_name ?? '',
            $order->client?->short_id ?? '',
            $order->customer_name,
            $order->customer_email,
            $order->customer_phone,
            $order->financial_status,
            $order->paid_at,
            $order->fulfillment_status,
            $order->fulfilled_at,
            $order->accepts_marketing ? 'yes' : 'no',
            $order->currency,
            $order->subtotal,
            $order->shipping_cost,
            $order->taxes,
            $order->total,
            $order->discount_code,
            $order->discount_amount,
            $order->shipping_method,
            $order->outstanding_balance,
            $order->refunded_amount,
            $order->cod_collected_amount,
            $order->cod_collected_at,
            $order->payment_method,
            $order->payment_reference,
            $item?->lineitem_quantity ?? '',
            $item?->lineitem_name ?? '',
            $item?->variant_name ?? '',
            $item?->lineitem_sku ?? '',
            $item?->lineitem_price ?? '',
            $item?->lineitem_compare_at_price ?? '',
            $item?->lineitem_discount ?? '',
            $item ? ($item->lineitem_requires_shipping ? 'true' : 'false') : '',
            $item ? ($item->lineitem_taxable ? 'true' : 'false') : '',
            $item?->lineitem_fulfillment_status ?? '',
            $order->billing_name,
            $order->billing_street,
            $order->billing_address1,
            $order->billing_address2,
            $order->billing_company,
            $order->billing_city,
            $order->billing_zip,
            $order->billing_province,
            $order->billing_country,
            $order->billing_phone,
            $order->shipping_name,
            $order->shipping_street,
            $order->shipping_address1,
            $order->shipping_address2,
            $order->shipping_company,
            $order->shipping_city,
            $order->shipping_zip,
            $order->shipping_province,
            $order->shipping_country,
            $order->shipping_phone,
            $shipment?->courier ?? '',
            $shipment?->tracking_number ?? '',
            $shipment?->status ?? '',
            $shipment?->courier_status_description ?? '',
            $shipment?->shipped_at ?? '',
            $shipment?->delivered_at ?? '',
            $shipment?->return_tracking_number ?? '',
            $order->notes,
            is_array($order->note_attributes) ? json_encode($order->note_attributes) : ($order->note_attributes ?? ''),
            is_array($order->tags) ? implode(', ', $order->tags) : ($order->tags ?? ''),
            is_array($order->shopify_raw_tags) ? implode(', ', $order->shopify_raw_tags) : ($order->shopify_raw_tags ?? ''),
            $order->risk_level,
            $order->source,
            $order->vendor,
            $order->utm_source,
            $order->utm_medium,
            $order->utm_campaign,
            $order->utm_id,
            $order->ip_address,
            $order->shopify_order_id,
            $order->shopify_shop_domain,
            $order->cancelled_at,
            $order->created_at,
            $order->updated_at,
        ];
    }
}
