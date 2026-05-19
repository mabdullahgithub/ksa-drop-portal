<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdersFromCsvSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('files/orders_export.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV not found at: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file.');
            return;
        }

        // Read headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->command->error('CSV has no headers.');
            fclose($handle);
            return;
        }

        // Trim BOM and whitespace from headers
        $headers = array_map(fn($h) => trim($h, "\xEF\xBB\xBF \t"), $headers);

        $imported  = 0;
        $duplicates = 0;
        $skipped   = 0;
        $errors    = [];
        $total     = 0;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $total++;

                // Skip completely empty rows
                if (empty(array_filter($row))) {
                    $skipped++;
                    continue;
                }

                // Pad/trim row to match header count
                $headerCount = count($headers);
                $rowCount    = count($row);
                if ($rowCount < $headerCount) {
                    $row = array_pad($row, $headerCount, '');
                } elseif ($rowCount > $headerCount) {
                    $row = array_slice($row, 0, $headerCount);
                }

                $data        = array_combine($headers, $row);
                $orderNumber = trim($data['Name'] ?? '');

                if (!$orderNumber) {
                    $skipped++;
                    continue;
                }

                // Skip duplicates
                if (Order::where('order_number', $orderNumber)->exists()) {
                    $duplicates++;
                    continue;
                }

                try {
                    $createdAt  = $this->parseDate($data['Created at'] ?? '');
                    $paidAt     = $this->parseDate($data['Paid at'] ?? '');
                    $fulfilledAt = $this->parseDate($data['Fulfilled at'] ?? '');
                    $cancelledAt = $this->parseDate($data['Cancelled at'] ?? '');

                    $fulfillmentStatus = strtolower(trim($data['Fulfillment Status'] ?? 'unfulfilled'));
                    $financialStatus   = strtolower(trim($data['Financial Status'] ?? 'pending'));

                    // Normalise statuses to known values
                    if (!in_array($fulfillmentStatus, ['pending', 'unfulfilled', 'fulfilled', 'cancelled'])) {
                        $fulfillmentStatus = 'unfulfilled';
                    }
                    if (!in_array($financialStatus, ['pending', 'paid', 'refunded', 'partially_refunded'])) {
                        $financialStatus = 'pending';
                    }

                    $order = Order::create([
                        'order_number'       => $orderNumber,
                        'customer_name'      => $data['Billing Name'] ?? $data['Shipping Name'] ?? null,
                        'customer_email'     => $data['Email'] ?? null,
                        'customer_phone'     => $data['Phone'] ?? $data['Billing Phone'] ?? $data['Shipping Phone'] ?? null,
                        'financial_status'   => $financialStatus,
                        'fulfillment_status' => $fulfillmentStatus,
                        'accepts_marketing'  => strtolower($data['Accepts Marketing'] ?? 'no') === 'yes',
                        'currency'           => $data['Currency'] ?? 'SAR',
                        'subtotal'           => $this->decimal($data['Subtotal'] ?? 0),
                        'shipping'           => $this->decimal($data['Shipping'] ?? 0),
                        'taxes'              => $this->decimal($data['Taxes'] ?? 0),
                        'total'              => $this->decimal($data['Total'] ?? 0),
                        'discount_code'      => $data['Discount Code'] ?? null,
                        'discount_amount'    => $this->decimal($data['Discount Amount'] ?? 0),
                        'shipping_method'    => $data['Shipping Method'] ?? null,
                        'billing_name'       => $data['Billing Name'] ?? null,
                        'billing_street'     => $data['Billing Street'] ?? null,
                        'billing_address1'   => $data['Billing Address1'] ?? null,
                        'billing_address2'   => $data['Billing Address2'] ?? null,
                        'billing_company'    => $data['Billing Company'] ?? null,
                        'billing_city'       => $data['Billing City'] ?? null,
                        'billing_zip'        => $data['Billing Zip'] ?? null,
                        'billing_province'   => $data['Billing Province'] ?? null,
                        'billing_country'    => $data['Billing Country'] ?? null,
                        'billing_phone'      => $data['Billing Phone'] ?? null,
                        'shipping_name'      => $data['Shipping Name'] ?? null,
                        'shipping_street'    => $data['Shipping Street'] ?? null,
                        'shipping_address1'  => $data['Shipping Address1'] ?? null,
                        'shipping_address2'  => $data['Shipping Address2'] ?? null,
                        'shipping_company'   => $data['Shipping Company'] ?? null,
                        'shipping_city'      => $data['Shipping City'] ?? null,
                        'shipping_zip'       => $data['Shipping Zip'] ?? null,
                        'shipping_province'  => $data['Shipping Province'] ?? null,
                        'shipping_country'   => $data['Shipping Country'] ?? null,
                        'shipping_phone'     => $data['Shipping Phone'] ?? null,
                        'notes'              => $data['Notes'] ?? null,
                        'note_attributes'    => $data['Note Attributes'] ?? null,
                        'payment_method'     => $data['Payment Method'] ?? null,
                        'payment_reference'  => $data['Payment Reference'] ?? $data['Payment References'] ?? null,
                        'refunded_amount'    => $this->decimal($data['Refunded Amount'] ?? 0),
                        'vendor'             => $data['Vendor'] ?? null,
                        'outstanding_balance' => $this->decimal($data['Outstanding Balance'] ?? 0),
                        'employee'           => $data['Employee'] ?? null,
                        'location'           => $data['Location'] ?? null,
                        'device_id'          => $data['Device ID'] ?? null,
                        'tags'               => !empty($data['Tags']) ? array_map('trim', explode(',', $data['Tags'])) : [],
                        'risk_level'         => $data['Risk Level'] ?? 'Low',
                        'source'             => $data['Source'] ?? null,
                        'utm_source'         => $data['utm_source'] ?? null,
                        'paid_at'            => $paidAt,
                        'fulfilled_at'       => $fulfilledAt,
                        'cancelled_at'       => $cancelledAt,
                        'created_at'         => $createdAt ?? now(),
                    ]);

                    // Order item
                    if (!empty(trim($data['Lineitem name'] ?? ''))) {
                        $order->items()->create([
                            'lineitem_quantity'           => (int) ($data['Lineitem quantity'] ?? 1),
                            'lineitem_name'               => trim($data['Lineitem name']),
                            'lineitem_price'              => $this->decimal($data['Lineitem price'] ?? 0),
                            'lineitem_compare_at_price'   => $this->decimal($data['Lineitem compare at price'] ?? 0),
                            'lineitem_sku'                => $data['Lineitem sku'] ?? null,
                            'lineitem_requires_shipping'  => strtolower($data['Lineitem requires shipping'] ?? 'false') === 'true',
                            'lineitem_taxable'            => strtolower($data['Lineitem taxable'] ?? 'false') === 'true',
                            'lineitem_fulfillment_status' => $data['Lineitem fulfillment status'] ?? 'unfulfilled',
                            'lineitem_discount'           => $this->decimal($data['Lineitem discount'] ?? 0),
                            'variant_name'               => $data['Lineitem variant title'] ?? null,
                        ]);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errors[] = "Row {$total} ({$orderNumber}): " . $e->getMessage();
                }
            }

            fclose($handle);
            DB::commit();

            $this->command->info("Import complete — imported: {$imported}, duplicates skipped: {$duplicates}, rows skipped: {$skipped}, total rows: {$total}");

            if (!empty($errors)) {
                $this->command->warn('Errors:');
                foreach (array_slice($errors, 0, 20) as $err) {
                    $this->command->warn("  {$err}");
                }
                if (count($errors) > 20) {
                    $this->command->warn('  ... and ' . (count($errors) - 20) . ' more');
                }
            }
        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();
            $this->command->error('Import failed: ' . $e->getMessage());
        }
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if (!$value) return null;
        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function decimal(mixed $value): float
    {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }
}
