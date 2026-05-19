<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsFromCsvSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('files/products_export_1.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV not found at: {$path}");
            return;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $this->command->error('Cannot open CSV file.');
            return;
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->command->error('CSV has no headers.');
            fclose($handle);
            return;
        }

        $headers = array_map(fn($h) => trim($h, "\xEF\xBB\xBF \t"), $headers);

        // Group all rows by handle; rows without a title are image-only continuation rows
        $products = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $headerCount = count($headers);
            $rowCount    = count($row);
            if ($rowCount < $headerCount) {
                $row = array_pad($row, $headerCount, '');
            } elseif ($rowCount > $headerCount) {
                $row = array_slice($row, 0, $headerCount);
            }

            $data   = array_combine($headers, $row);
            $handle2 = trim($data['Handle'] ?? '');

            if (!$handle2) {
                continue;
            }

            if (!isset($products[$handle2])) {
                $products[$handle2] = ['main' => null, 'images' => []];
            }

            $imageSrc = trim($data['Image Src'] ?? '');
            $imagePos = (int) ($data['Image Position'] ?? 1);
            $imageAlt = trim($data['Image Alt Text'] ?? '');

            if ($imageSrc) {
                $products[$handle2]['images'][] = [
                    'src'      => $imageSrc,
                    'position' => $imagePos ?: 1,
                    'alt_text' => $imageAlt ?: null,
                ];
            }

            // Only the row that carries title/price data is the main product row
            if (trim($data['Title'] ?? '') !== '' && $products[$handle2]['main'] === null) {
                $products[$handle2]['main'] = $data;
            }
        }

        fclose($handle);

        $imported   = 0;
        $duplicates = 0;
        $skipped    = 0;
        $total      = count($products);

        DB::beginTransaction();
        try {
            foreach ($products as $productHandle => $info) {
                $data = $info['main'];

                if (!$data) {
                    $skipped++;
                    continue;
                }

                if (Product::where('handle', $productHandle)->exists()) {
                    $duplicates++;
                    continue;
                }

                try {
                    $images = $info['images'];
                    usort($images, fn($a, $b) => $a['position'] <=> $b['position']);
                    $primaryImage = $images[0]['src'] ?? null;

                    $product = Product::create([
                        'handle'                         => $productHandle,
                        'title'                          => trim($data['Title']),
                        'body_html'                      => $data['Body (HTML)'] ?: null,
                        'vendor'                         => $data['Vendor'] ?: null,
                        'product_category'               => $data['Product Category'] ?: null,
                        'type'                           => $data['Type'] ?: null,
                        'tags'                           => $this->parseTags($data['Tags'] ?? ''),
                        'published'                      => $this->bool($data['Published'] ?? 'true'),
                        'variant_sku'                    => $data['Variant SKU'] ?: null,
                        'variant_price'                  => $this->decimal($data['Variant Price'] ?? ''),
                        'variant_compare_at_price'       => $this->decimal($data['Variant Compare At Price'] ?? ''),
                        'cost_per_item'                  => $this->decimal($data['Cost per item'] ?? ''),
                        'variant_inventory_qty'          => (int) ($data['Variant Inventory Qty'] ?? 0),
                        'variant_inventory_policy'       => $data['Variant Inventory Policy'] ?: 'deny',
                        'variant_fulfillment_service'    => $data['Variant Fulfillment Service'] ?: null,
                        'variant_requires_shipping'      => $this->bool($data['Variant Requires Shipping'] ?? 'true'),
                        'variant_taxable'                => $this->bool($data['Variant Taxable'] ?? 'true'),
                        'variant_grams'                  => $this->decimal($data['Variant Grams'] ?? ''),
                        'variant_barcode'                => $data['Variant Barcode'] ?: null,
                        'option1_name'                   => $data['Option1 Name'] ?: null,
                        'option1_value'                  => $data['Option1 Value'] ?: null,
                        'option2_name'                   => $data['Option2 Name'] ?: null,
                        'option2_value'                  => $data['Option2 Value'] ?: null,
                        'option3_name'                   => $data['Option3 Name'] ?: null,
                        'option3_value'                  => $data['Option3 Value'] ?: null,
                        'seo_title'                      => $data['SEO Title'] ?: null,
                        'seo_description'                => $data['SEO Description'] ?: null,
                        'available_in'                   => $this->parseAvailableIn($data['available_in (product.metafields.custom.available_in)'] ?? ''),
                        'uae_prices'                     => $this->parseUaePrices($data['uae prices (product.metafields.custom.uae_prices)'] ?? ''),
                        'included_saudi_arabia'          => $this->bool($data['Included / Saudi Arabia'] ?? 'true'),
                        'price_saudi_arabia'             => $this->decimal($data['Price / Saudi Arabia'] ?? ''),
                        'compare_at_price_saudi_arabia'  => $this->decimal($data['Compare At Price / Saudi Arabia'] ?? ''),
                        'included_pakistan'              => $this->bool($data['Included / Pakistan'] ?? 'false'),
                        'price_pakistan'                 => $this->decimal($data['Price / Pakistan'] ?? ''),
                        'compare_at_price_pakistan'      => $this->decimal($data['Compare At Price / Pakistan'] ?? ''),
                        'status'                         => $this->parseStatus($data['Status'] ?? 'active'),
                        'primary_image'                  => $primaryImage,
                    ]);

                    foreach ($images as $img) {
                        ProductImage::create([
                            'product_id' => $product->id,
                            'src'        => $img['src'],
                            'position'   => $img['position'],
                            'alt_text'   => $img['alt_text'],
                        ]);
                    }

                    $imported++;
                } catch (\Exception $e) {
                    $skipped++;
                    $this->command->warn("Skipped '{$productHandle}': " . $e->getMessage());
                }
            }

            DB::commit();
            $this->command->info("Import complete — imported: {$imported}, duplicates skipped: {$duplicates}, rows skipped: {$skipped}, total products: {$total}");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Import failed: ' . $e->getMessage());
        }
    }

    private function parseTags(string $value): array
    {
        // Shopify exports prepend a single quote; strip it
        $value = ltrim(trim($value), "'");
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function parseAvailableIn(string $value): ?array
    {
        $value = ltrim(trim($value), "'");
        if ($value === '') {
            return null;
        }
        // Values separated by newlines or commas
        $parts = preg_split('/[\n,]+/', $value);
        $result = array_values(array_filter(array_map('trim', $parts)));
        return $result ?: null;
    }

    private function parseUaePrices(string $value): ?array
    {
        $value = ltrim(trim($value), "'");
        if ($value === '') {
            return null;
        }
        // Could be a single price or a list; store as array of floats
        $parts = preg_split('/[\n,]+/', $value);
        $result = array_values(array_filter(array_map(fn($p) => is_numeric(trim($p)) ? (float) trim($p) : null, $parts)));
        return $result ?: null;
    }

    private function bool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
    }

    private function decimal(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', $value);
        return $clean !== '' ? (float) $clean : null;
    }

    private function parseStatus(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['active', 'draft', 'archived']) ? $v : 'active';
    }
}
