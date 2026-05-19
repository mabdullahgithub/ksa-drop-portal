<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvFile = base_path('files/orders_export.csv');

        if (!file_exists($csvFile)) {
            $this->command->error('CSV file not found at: ' . $csvFile);
            return;
        }

        $this->command->info('Reading orders from CSV...');

        $file = fopen($csvFile, 'r');
        $headers = fgetcsv($file); // Read headers

        $orders = [];
        $currentOrderNumber = null;
        $currentOrderData = null;

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 2) {
                continue;
            }

            $data = array_combine($headers, $row);
            $orderNumber = $data['Name'] ?? null;

            // Skip if no order number
            if (empty($orderNumber)) {
                continue;
            }

            // If we encounter a new order number, save the previous order
            if ($orderNumber !== $currentOrderNumber && $currentOrderData !== null) {
                $orders[] = $currentOrderData;
            }

            // If this is a new order, create new order data
            if ($orderNumber !== $currentOrderNumber) {
                $currentOrderNumber = $orderNumber;
                $currentOrderData = $this->parseOrderData($data);
                $currentOrderData['items'] = [];
            }

            // Add line item to current order
            if (!empty($data['Lineitem name'])) {
                $currentOrderData['items'][] = $this->parseLineItem($data);
            }
        }

        // Don't forget to add the last order
        if ($currentOrderData !== null) {
            $orders[] = $currentOrderData;
        }

        fclose($file);

        $this->command->info('Found ' . count($orders) . ' orders. Seeding database...');

        DB::transaction(function () use ($orders) {
            foreach ($orders as $orderData) {
                $items = $orderData['items'];
                unset($orderData['items']);

                $order = Order::create($orderData);

                foreach ($items as $itemData) {
                    $order->items()->create($itemData);
                }
            }
        });

        $this->command->info('Successfully seeded ' . count($orders) . ' orders!');
    }

    /**
     * Parse order data from CSV row.
     */
    private function parseOrderData(array $data): array
    {
        // Parse note attributes
        $noteAttributes = $this->parseNoteAttributes($data['Note Attributes'] ?? '');

        // Parse tags
        $tags = !empty($data['Tags']) ? explode(', ', $data['Tags']) : [];

        return [
            'order_number' => $data['Name'] ?? '',
            'shopify_order_id' => $data['Id'] ?? null,
            'customer_name' => $data['Billing Name'] ?? null,
            'customer_email' => $data['Email'] ?? null,
            'customer_phone' => $this->cleanPhone($data['Phone'] ?? $data['Billing Phone'] ?? null),
            'billing_name' => $data['Billing Name'] ?? null,
            'billing_street' => $data['Billing Street'] ?? null,
            'billing_address1' => $data['Billing Address1'] ?? null,
            'billing_address2' => $data['Billing Address2'] ?? null,
            'billing_company' => $data['Billing Company'] ?? null,
            'billing_city' => $data['Billing City'] ?? null,
            'billing_zip' => $data['Billing Zip'] ?? null,
            'billing_province' => $data['Billing Province'] ?? null,
            'billing_country' => $data['Billing Country'] ?? null,
            'billing_phone' => $this->cleanPhone($data['Billing Phone'] ?? null),
            'shipping_name' => $data['Shipping Name'] ?? null,
            'shipping_street' => $data['Shipping Street'] ?? null,
            'shipping_address1' => $data['Shipping Address1'] ?? null,
            'shipping_address2' => $data['Shipping Address2'] ?? null,
            'shipping_company' => $data['Shipping Company'] ?? null,
            'shipping_city' => $data['Shipping City'] ?? null,
            'shipping_zip' => $data['Shipping Zip'] ?? null,
            'shipping_province' => $data['Shipping Province'] ?? null,
            'shipping_country' => $data['Shipping Country'] ?? null,
            'shipping_phone' => $this->cleanPhone($data['Shipping Phone'] ?? null),
            'financial_status' => strtolower($data['Financial Status'] ?? 'pending'),
            'fulfillment_status' => strtolower($data['Fulfillment Status'] ?? 'unfulfilled'),
            'payment_method' => $data['Payment Method'] ?? null,
            'payment_reference' => $data['Payment Reference'] ?? null,
            'currency' => $data['Currency'] ?? 'SAR',
            'subtotal' => $this->parseDecimal($data['Subtotal'] ?? 0),
            'shipping_cost' => $this->parseDecimal($data['Shipping'] ?? 0),
            'taxes' => $this->parseDecimal($data['Taxes'] ?? 0),
            'total' => $this->parseDecimal($data['Total'] ?? 0),
            'discount_code' => $data['Discount Code'] ?? null,
            'discount_amount' => $this->parseDecimal($data['Discount Amount'] ?? 0),
            'shipping_method' => $data['Shipping Method'] ?? null,
            'outstanding_balance' => $this->parseDecimal($data['Outstanding Balance'] ?? 0),
            'refunded_amount' => $this->parseDecimal($data['Refunded Amount'] ?? 0),
            'paid_at' => $this->parseDate($data['Paid at'] ?? null),
            'fulfilled_at' => $this->parseDate($data['Fulfilled at'] ?? null),
            'cancelled_at' => $this->parseDate($data['Cancelled at'] ?? null),
            'notes' => $data['Notes'] ?? null,
            'note_attributes' => $noteAttributes,
            'tags' => $tags,
            'risk_level' => $data['Risk Level'] ?? null,
            'source' => $data['Source'] ?? null,
            'vendor' => $data['Vendor'] ?? null,
            'utm_source' => $noteAttributes['utm_source'] ?? null,
            'utm_medium' => $noteAttributes['utm_medium'] ?? null,
            'utm_campaign' => $noteAttributes['utm_campaign'] ?? null,
            'utm_id' => $noteAttributes['utm_id'] ?? null,
            'ip_address' => $noteAttributes['IP Address'] ?? null,
            'accepts_marketing' => strtolower($data['Accepts Marketing'] ?? 'no') === 'yes',
            'created_at' => $this->parseDate($data['Created at'] ?? null),
        ];
    }

    /**
     * Parse line item data from CSV row.
     */
    private function parseLineItem(array $data): array
    {
        // Extract variant name from the lineitem name (e.g., "- Gray", "- Brown")
        $lineitemName = $data['Lineitem name'] ?? '';
        $variantName = null;

        if (preg_match('/\s-\s(.+)$/', $lineitemName, $matches)) {
            $variantName = $matches[1];
        }

        return [
            'lineitem_name' => $lineitemName,
            'lineitem_quantity' => (int)($data['Lineitem quantity'] ?? 1),
            'lineitem_price' => $this->parseDecimal($data['Lineitem price'] ?? 0),
            'lineitem_compare_at_price' => $this->parseDecimal($data['Lineitem compare at price'] ?? null),
            'lineitem_sku' => $data['Lineitem sku'] ?? null,
            'lineitem_requires_shipping' => strtolower($data['Lineitem requires shipping'] ?? 'true') === 'true',
            'lineitem_taxable' => strtolower($data['Lineitem taxable'] ?? 'true') === 'true',
            'lineitem_fulfillment_status' => strtolower($data['Lineitem fulfillment status'] ?? 'pending'),
            'lineitem_discount' => $this->parseDecimal($data['Lineitem discount'] ?? 0),
            'variant_name' => $variantName,
        ];
    }

    /**
     * Parse note attributes from the notes field.
     */
    private function parseNoteAttributes(string $notes): array
    {
        $attributes = [];
        $lines = explode("\n", $notes);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse key: value pairs
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Clean up Arabic labels
                $key = str_replace(['Phone Number هاتف', 'Full Name'], ['phone', 'name'], $key);

                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Parse decimal value.
     */
    private function parseDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }

    /**
     * Parse date string.
     */
    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        try {
            return date('Y-m-d H:i:s', strtotime($date));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean phone number.
     */
    private function cleanPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove common prefixes and clean up
        $phone = str_replace(['966/', '+966', ' '], ['', '+966', ''], $phone);

        // If it starts with 0, add +966
        if (strpos($phone, '0') === 0) {
            $phone = '+966' . substr($phone, 1);
        }

        return $phone;
    }
}
