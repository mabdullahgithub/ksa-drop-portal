<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Order;
use App\Models\StockScan;
use App\Models\StockScanItem;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Post yesterday's warehouse scans to Slack.
 *
 * Every parcel an inventory manager scanned OUT or IN, grouped by the store it
 * belongs to, with what each scan did to stock: the count before it and the
 * count after it. Stores are listed busiest first, by the number of orders
 * scanned.
 *
 * It reads the scan log (stock_scans / stock_scan_items) and nothing else, so
 * the counts are the ones the scan itself left behind — not today's stock
 * worked backwards. Order and revenue figures are not part of it; those are on
 * the dashboard.
 *
 * The reporting day is a *business* day (Asia/Riyadh), not a UTC one, and it is
 * measured on the phone's clock at the moment of the scan (occurred_at) — the
 * same clock the manager's own "today" figures use.
 */
class SendDailyStockReport extends Command
{
    protected $signature = 'report:daily
                            {--date= : Report on this business day (Y-m-d) instead of yesterday}
                            {--dry-run : Print the report instead of posting it to Slack}';

    protected $description = "Post the previous day's warehouse OUT/IN scans and stock counts to Slack";

    /** Slack refuses a message with more than 50 blocks. */
    private const BLOCKS_PER_MESSAGE = 45;

    /** Slack refuses a section over 3,000 characters; this leaves room for its title and fences. */
    private const SECTION_LIMIT = 2800;

    public function handle(): int
    {
        $timezone = (string) config('services.slack.ops_timezone', 'Asia/Riyadh');

        $day = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDay()->startOfDay();

        // occurred_at is stored in UTC.
        $report = $this->gather($day->utc(), $day->addDay()->utc());
        $blocks = $this->render($report, $day, $timezone);

        if ($this->option('dry-run')) {
            $this->line(json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $webhook = (string) config('services.slack.ops_webhook');

        if ($webhook === '') {
            $this->error('SLACK_OPS_WEBHOOK is not set — nothing to post to.');

            return self::FAILURE;
        }

        $summary = sprintf(
            'KSA Drop stock — %s: %d parcel(s) out, %d in, %d store(s)',
            $day->format('j M'),
            $report['out']['parcels'],
            $report['in']['parcels'],
            $report['stores']->count()
        );

        // A busy day does not fit in one message, so it goes as several, in order.
        foreach (array_chunk($blocks, self::BLOCKS_PER_MESSAGE) as $message) {
            $response = Http::timeout(15)->post($webhook, [
                'text'   => $summary,
                'blocks' => $message,
            ]);

            if ($response->failed()) {
                // Slack answers a bad webhook with a plain-text body, not JSON.
                Log::error('Daily stock report could not be posted to Slack', [
                    'channel' => $this->destination(),
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);

                $this->error("Slack rejected the report: {$response->status()} {$response->body()}");

                return self::FAILURE;
            }
        }

        $this->info(sprintf(
            'Posted the report for %s to %s.',
            $day->toDateString(),
            $this->destination()
        ));

        return self::SUCCESS;
    }

    /**
     * Where the configured webhook posts, for the command's own output. Falls
     * back to a generic word rather than an empty string so a message never
     * reads "Posted the report for 2026-09-06 to .".
     */
    private function destination(): string
    {
        $channel = (string) config('services.slack.ops_channel');

        return $channel === '' ? 'Slack' : '#' . ltrim($channel, '#');
    }

    /**
     * @return array<string, mixed>
     */
    private function gather(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // In the order the server took them, which is the order stock really
        // moved in: a phone that was offline sends its scans late, and each
        // "before" has to follow the "after" of the scan ahead of it.
        $scans = StockScan::with([
            'items',
            'rider:id,name',
            'shipment:id,tracking_number',
            // A scanned parcel still left the warehouse if its order was
            // deleted or is hidden awaiting the client's review.
            'order' => fn ($query) => $query
                ->withoutGlobalScope('shopify_visible')
                ->withTrashed()
                ->select(['id', 'order_number', 'client_id', 'shopify_shop_domain']),
        ])
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<', $to)
            ->orderBy('id')
            ->get();

        // Named by the client's company, not the myshopify handle — the handles
        // are random strings nobody recognises. Trashed clients keep their name.
        $clientNames = Client::withTrashed()
            ->whereIn('id', $scans->pluck('order.client_id')->filter()->unique())
            ->pluck('company_name', 'id');

        $stores = $scans
            ->groupBy(fn (StockScan $scan) => $this->storeKey($scan->order))
            ->map(fn (Collection $group) => [
                'name'     => $this->storeName($group->first()->order, $clientNames),
                'orders'   => $group->map(fn (StockScan $scan) => $scan->order_id ?? 'parcel-' . $scan->shipment_id)->unique()->count(),
                'out'      => $this->tally($group, StockScan::OUT),
                'in'       => $this->tally($group, StockScan::IN),
                'products' => $this->products($group),
                'scans'    => $group,
            ])
            // Busiest store first; the name settles a tie so two runs agree.
            ->sortBy([['orders', 'desc'], ['name', 'asc']])
            ->values();

        $products = $stores->flatMap(fn (array $store) => $store['products']);

        return [
            'out'        => $this->tally($scans, StockScan::OUT),
            'in'         => $this->tally($scans, StockScan::IN),
            'orders'     => $stores->sum('orders'),
            'stores'     => $stores,
            'not_linked' => $products->where('linked', false)->count(),
            'below_zero' => $products->filter(fn (array $product) => $product['linked'] && $product['after'] < 0)
                ->pluck('name')
                ->unique()
                ->values(),
        ];
    }

    /**
     * One store's parcels share a client; an order with no client falls back
     * to the shop it came from, and one with neither is nobody's.
     */
    private function storeKey(?Order $order): string
    {
        return match (true) {
            $order?->client_id !== null          => 'client:' . $order->client_id,
            filled($order?->shopify_shop_domain) => 'shop:' . $order->shopify_shop_domain,
            default                              => 'none',
        };
    }

    private function storeName(?Order $order, Collection $clientNames): string
    {
        if ($order?->client_id !== null && filled($clientNames[$order->client_id] ?? null)) {
            return (string) $clientNames[$order->client_id];
        }

        return filled($order?->shopify_shop_domain)
            ? str_replace('.myshopify.com', '', (string) $order->shopify_shop_domain)
            : 'No store';
    }

    /**
     * @return array{parcels: int, pieces: int}
     */
    private function tally(Collection $scans, string $direction): array
    {
        $scans = $scans->where('direction', $direction);

        return ['parcels' => $scans->count(), 'pieces' => (int) $scans->sum('pieces')];
    }

    /**
     * Each product these scans touched: units out, units in, and its stock
     * before the first of them and after the last.
     *
     * @return Collection<int, array{name: string, sku: ?string, out: int, in: int, before: ?int, after: ?int, linked: bool}>
     */
    private function products(Collection $scans): Collection
    {
        $products = [];

        foreach ($scans as $scan) {
            foreach ($scan->items as $item) {
                $key = match (true) {
                    $item->product_id !== null        => 'product:' . $item->product_id,
                    $item->client_product_id !== null => 'client-product:' . $item->client_product_id,
                    // Matched no product, so no stock moved for it.
                    default                           => 'none:' . ($item->sku ?? $item->name),
                };

                $products[$key] ??= [
                    'name'   => $item->name,
                    'sku'    => $item->sku,
                    'out'    => 0,
                    'in'     => 0,
                    'before' => $this->stockBefore($item),
                    'after'  => null,
                    'linked' => $item->isMatched(),
                ];

                $products[$key][$scan->direction] += abs($item->quantity);
                $products[$key]['after'] = $item->stock_after;
            }
        }

        return collect($products)
            ->sortByDesc(fn (array $product) => $product['out'] + $product['in'])
            ->values();
    }

    /**
     * quantity is signed (below zero going out), so taking it back off the
     * count the scan left gives the count it found.
     */
    private function stockBefore(StockScanItem $item): ?int
    {
        return $item->stock_after === null ? null : $item->stock_after - $item->quantity;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, mixed>>
     */
    private function render(array $report, CarbonImmutable $day, string $timezone): array
    {
        $blocks = [[
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => '📦 Stock scans — ' . $day->format('l, j F'), 'emoji' => true],
        ]];

        if ($report['stores']->isEmpty()) {
            $blocks[] = $this->section('No parcels were scanned OUT or IN.');
            $blocks[] = $this->footer($day, $timezone);

            return $blocks;
        }

        $blocks[] = [
            'type'   => 'section',
            'fields' => [
                $this->field('Scanned OUT', $this->parcels($report['out'])),
                $this->field('Scanned IN', $this->parcels($report['in'])),
                $this->field('Stores', number_format($report['stores']->count())),
                $this->field('Orders scanned', number_format($report['orders'])),
            ],
        ];

        array_push($blocks, ...$this->codeSections('Stores by orders scanned', [
            sprintf('%s %6s %5s %5s', mb_str_pad('Store', 24), 'Orders', 'OUT', 'IN'),
            ...$report['stores']->map(fn (array $store) => sprintf(
                '%s %6d %5d %5d',
                // mb_* so Arabic names are cut and padded by character, not
                // byte, and the columns still line up.
                mb_str_pad(mb_strimwidth($store['name'], 0, 24, '…'), 24),
                $store['orders'],
                $store['out']['parcels'],
                $store['in']['parcels']
            )),
        ]));

        if ($alerts = $this->alertLines($report)) {
            $blocks[] = $this->section("*⚠️ Needs attention*\n" . implode("\n", $alerts));
        }

        foreach ($report['stores'] as $store) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = $this->section(sprintf(
                "*🏬 %s* — %d order(s)\nOUT %s · IN %s",
                $this->escape($store['name']),
                $store['orders'],
                $this->parcels($store['out']),
                $this->parcels($store['in'])
            ));

            array_push($blocks, ...$this->codeSections('Stock, before → after the day\'s scans', [
                sprintf('%s %4s %4s  %s', mb_str_pad('Product', 26), 'OUT', 'IN', 'Stock'),
                ...$store['products']->map(fn (array $product) => sprintf(
                    '%s %4d %4d  %s',
                    mb_str_pad(mb_strimwidth($this->label($product['name'], $product['sku']), 0, 26, '…'), 26),
                    $product['out'],
                    $product['in'],
                    $this->stockChange($product['before'], $product['after'])
                )),
            ]));

            array_push($blocks, ...$this->codeSections(
                'Parcels scanned',
                $store['scans']->map(fn (StockScan $scan) => $this->scanEntry($scan, $timezone))->all()
            ));
        }

        $blocks[] = $this->footer($day, $timezone);

        return $blocks;
    }

    /**
     * One parcel: when, which way, which order, who scanned it — then each
     * item with the stock this scan found and the stock it left.
     */
    private function scanEntry(StockScan $scan, string $timezone): string
    {
        $lines = [sprintf(
            '%s %-3s #%s · %s · %s',
            $scan->occurred_at->setTimezone($timezone)->format('H:i'),
            strtoupper($scan->direction),
            $scan->order?->order_number ?? '—',
            $scan->shipment?->tracking_number ?? '—',
            $scan->rider?->name ?? '—'
        )];

        foreach ($scan->items as $item) {
            $lines[] = sprintf(
                '   %s ×%d  %s',
                mb_strimwidth($this->label($item->name, $item->sku), 0, 34, '…'),
                abs($item->quantity),
                $this->stockChange($this->stockBefore($item), $item->stock_after)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Only what is actually wrong. A warning that prints every morning is a
     * warning nobody reads.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function alertLines(array $report): array
    {
        $alerts = [];

        if ($report['not_linked'] > 0) {
            $alerts[] = sprintf(
                '• *%d item(s) are not linked to a product* — they were scanned, but no stock moved for them',
                $report['not_linked']
            );
        }

        if ($report['below_zero']->isNotEmpty()) {
            $alerts[] = sprintf(
                '• *%d product(s) ended below zero*: %s',
                $report['below_zero']->count(),
                $this->escape($report['below_zero']->take(10)->implode(', '))
            );
        }

        return $alerts;
    }

    private function label(string $name, ?string $sku): string
    {
        return filled($sku) ? "{$name} [{$sku}]" : $name;
    }

    private function stockChange(?int $before, ?int $after): string
    {
        return $before === null || $after === null ? 'not linked' : "{$before} → {$after}";
    }

    /**
     * @param  array{parcels: int, pieces: int}  $tally
     */
    private function parcels(array $tally): string
    {
        return sprintf('%s parcel(s), %s pcs', number_format($tally['parcels']), number_format($tally['pieces']));
    }

    /**
     * Lines as one or more code blocks, none over Slack's section limit. An
     * entry is never split across two blocks, so a parcel stays with its items.
     *
     * @param  array<int, string>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function codeSections(string $title, array $entries): array
    {
        $chunks = [];
        $buffer = '';

        foreach ($entries as $entry) {
            $entry = mb_substr($this->escape($entry), 0, self::SECTION_LIMIT);

            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($entry) + 1 > self::SECTION_LIMIT) {
                $chunks[] = $buffer;
                $buffer = '';
            }

            $buffer .= ($buffer === '' ? '' : "\n") . $entry;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_map(
            fn (string $chunk, int $index) => $this->section(($index === 0 ? "*{$title}*\n" : '') . "```\n{$chunk}\n```"),
            $chunks,
            array_keys($chunks)
        );
    }

    /**
     * Slack reads &, < and > as markup even inside a code block, and a
     * backtick in a product name would close the block early.
     */
    private function escape(string $text): string
    {
        return str_replace(['&', '<', '>', '`'], ['&amp;', '&lt;', '&gt;', "'"], $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function footer(CarbonImmutable $day, string $timezone): array
    {
        return [
            'type'     => 'context',
            'elements' => [[
                'type' => 'mrkdwn',
                'text' => sprintf(
                    '%s · business day in %s · <%s|open the portal>',
                    $day->toDateString(),
                    $timezone,
                    rtrim((string) config('app.url'), '/')
                ),
            ]],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function field(string $label, string $value): array
    {
        return ['type' => 'mrkdwn', 'text' => "*{$label}*\n{$value}"];
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $text): array
    {
        return ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]];
    }
}
