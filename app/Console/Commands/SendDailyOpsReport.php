<?php

namespace App\Console\Commands;

use App\Models\ClientShopifyConnection;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShopifySyncFailure;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Post yesterday's order and pipeline report to Slack.
 *
 * The point of this command is not the order count — that is on the dashboard
 * already. It is the pipeline block and the alerts: the things nobody thinks to
 * go and look at, which are exactly the things that fail quietly. A day where
 * webhooks stopped arriving looks identical to a slow day unless something says
 * so out loud.
 *
 * Alerts are conditional. A report that carries the same three warnings every
 * morning stops being read within a week, so a threshold that is not crossed
 * prints nothing at all and the message stays short on a normal day.
 *
 * The reporting day is a *business* day (Asia/Riyadh), not a UTC one. Orders
 * peak between 20:00 and 02:00 local, so a UTC boundary would split the busiest
 * stretch of the evening across two reports.
 */
class SendDailyOpsReport extends Command
{
    protected $signature = 'report:daily
                            {--date= : Report on this business day (Y-m-d) instead of yesterday}
                            {--dry-run : Print the report instead of posting it to Slack}';

    protected $description = "Post the previous day's order and pipeline report to Slack";

    /** Queue depth above which the worker is considered to be falling behind. */
    private const QUEUE_DEPTH_ALERT = 50;

    /** Cities Shopify sends when the checkout never asked for one. */
    private const EMPTY_CITIES = ['', '-', '--', 'n/a', 'na', 'none'];

    public function handle(): int
    {
        $timezone = (string) config('services.slack.ops_timezone', 'Asia/Riyadh');

        $day = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDay()->startOfDay();

        // Everything below compares against created_at, which is stored in UTC.
        $from = $day->utc();
        $to   = $day->addDay()->utc();

        $report = $this->gather($day, $from, $to, $timezone);
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
            'KSA Drop — %s: %d orders, SAR %s',
            $day->format('j M'),
            $report['orders'],
            number_format($report['revenue'])
        );

        $response = Http::timeout(15)->post($webhook, [
            'text'   => $summary,
            'blocks' => $blocks,
        ]);

        if ($response->failed()) {
            // Slack answers a bad webhook with a plain-text body, not JSON.
            Log::error('Daily ops report could not be posted to Slack', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            $this->error("Slack rejected the report: {$response->status()} {$response->body()}");

            return self::FAILURE;
        }

        $this->info("Posted the report for {$day->toDateString()} to Slack.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function gather(CarbonImmutable $day, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        // Orders awaiting client review are hidden from every normal query by a
        // global scope. They still arrived, so the report counts them.
        $orders = Order::withoutGlobalScope('shopify_visible')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->get(['id', 'created_at', 'total', 'client_id', 'shopify_shop_domain', 'shipping_city']);

        $revenue = (float) $orders->sum('total');
        $count   = $orders->count();

        // One aggregate over the whole preceding week rather than seven grouped
        // rows: DATE() grouping is dialect-specific and the daily average is all
        // this needs. A quiet day is as worth flagging as a busy one.
        $baseline = Order::withoutGlobalScope('shopify_visible')
            ->where('created_at', '>=', $from->subDays(7))
            ->where('created_at', '<', $from)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $hourly = $orders
            ->groupBy(fn (Order $order) => $order->created_at->setTimezone($timezone)->format('H'))
            ->map->count()
            ->sortDesc();

        $stores = $orders
            ->groupBy(fn (Order $order) => $order->shopify_shop_domain ?: 'manual entry')
            ->map(fn ($group, $shop) => [
                'shop'    => $shop,
                'orders'  => $group->count(),
                'revenue' => (float) $group->sum('total'),
            ])
            ->sortByDesc('orders')
            ->values();

        $missingCity = $orders
            ->filter(fn (Order $order) => in_array(
                strtolower(trim((string) $order->shipping_city)),
                self::EMPTY_CITIES,
                true
            ))
            ->count();

        $shipments = Shipment::where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->get(['courier'])
            ->groupBy('courier')
            ->map->count();

        return [
            'orders'           => $count,
            'revenue'          => $revenue,
            'aov'              => $count > 0 ? $revenue / $count : 0.0,
            'baseline_orders'  => ((int) $baseline->orders) / 7,
            'baseline_revenue' => ((float) $baseline->revenue) / 7,
            'peak_hour'        => $hourly->keys()->first(),
            'peak_orders'      => $hourly->first() ?? 0,
            'stores'           => $stores,
            'missing_city'     => $missingCity,
            'shipments'        => $shipments,
            'webhooks'         => $this->webhookCounts($from, $to),
            'app_errors'       => $this->appErrorCount($from, $to),
            'failures_opened'  => ShopifySyncFailure::where('created_at', '>=', $from)
                ->where('created_at', '<', $to)
                ->count(),
            'failures_open'    => ShopifySyncFailure::unresolved()->count(),
            'failed_jobs'      => DB::table('failed_jobs')->count(),
            'queue_depth'      => DB::table('jobs')->count(),
            'unclaimed'        => ClientShopifyConnection::whereNull('client_id')
                ->where('status', 'active')
                ->count(),
        ];
    }

    /**
     * Count webhook outcomes out of the Shopify log.
     *
     * Deliveries are not stored anywhere — a webhook that syncs cleanly leaves
     * only a log line — so this is the only record of how many arrived. The logs
     * rotate on the UTC date, so a business day spans two files.
     *
     * @return array{synced: int, parked: int, available: bool}
     */
    private function webhookCounts(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $synced = 0;
        $parked = 0;
        $found  = false;

        for ($date = $from->startOfDay(); $date < $to; $date = $date->addDay()) {
            $path = $this->dailyLogPath('shopify', $date);

            if ($path === null || ! is_readable($path)) {
                continue;
            }

            $found  = true;
            $handle = fopen($path, 'rb');

            while (($line = fgets($handle)) !== false) {
                // [2026-09-06 18:47:03] production.INFO: ...
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $match)) {
                    continue;
                }

                $at = CarbonImmutable::parse($match[1], 'UTC');

                if ($at < $from || $at >= $to) {
                    continue;
                }

                if (str_contains($line, 'Shopify order synced from webhook')) {
                    $synced++;
                } elseif (str_contains($line, 'no active linked connection')) {
                    $parked++;
                }
            }

            fclose($handle);
        }

        return ['synced' => $synced, 'parked' => $parked, 'available' => $found];
    }

    private function appErrorCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $errors = 0;

        for ($date = $from->startOfDay(); $date < $to; $date = $date->addDay()) {
            $path = $this->dailyLogPath('daily', $date);

            if ($path === null || ! is_readable($path)) {
                continue;
            }

            $handle = fopen($path, 'rb');

            while (($line = fgets($handle)) !== false) {
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $match)) {
                    continue;
                }

                $at = CarbonImmutable::parse($match[1], 'UTC');

                if ($at >= $from && $at < $to) {
                    $errors++;
                }
            }

            fclose($handle);
        }

        return $errors;
    }

    /**
     * The file a `daily` log channel writes on a given date.
     *
     * Derived from the channel's configured path rather than rebuilt from
     * storage_path(), so the report keeps reading the right file if a channel is
     * ever pointed elsewhere — and so a test can aim it at an empty directory
     * instead of asserting against whatever happens to be in storage/logs.
     */
    private function dailyLogPath(string $channel, CarbonImmutable $date): ?string
    {
        $path = config("logging.channels.{$channel}.path");

        if (! is_string($path) || $path === '') {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $stem      = $extension === '' ? $path : substr($path, 0, -(strlen($extension) + 1));
        $suffix    = $extension === '' ? '' : '.' . $extension;

        return $stem . '-' . $date->format('Y-m-d') . $suffix;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, mixed>>
     */
    private function render(array $report, CarbonImmutable $day, string $timezone): array
    {
        $blocks = [[
            'type' => 'header',
            'text' => ['type' => 'plain_text', 'text' => '📦 KSA Drop — ' . $day->format('l, j F'), 'emoji' => true],
        ]];

        $peak = $report['peak_orders'] > 0
            ? sprintf('%s:00 (%d)', $report['peak_hour'], $report['peak_orders'])
            : '—';

        $blocks[] = [
            'type'   => 'section',
            'fields' => [
                $this->field('Orders', number_format($report['orders']) . '  ' . $this->trend($report['orders'], $report['baseline_orders'])),
                $this->field('Value', 'SAR ' . number_format($report['revenue']) . '  ' . $this->trend($report['revenue'], $report['baseline_revenue'])),
                $this->field('Average order', 'SAR ' . number_format($report['aov'])),
                $this->field('Peak hour', $peak),
            ],
        ];

        if ($report['stores']->isNotEmpty()) {
            $rows = $report['stores']
                ->take(6)
                ->map(fn (array $store) => sprintf(
                    '%-26s %4d   SAR %s',
                    str_replace('.myshopify.com', '', $store['shop']),
                    $store['orders'],
                    number_format($store['revenue'])
                ))
                ->implode("\n");

            $blocks[] = $this->section("*Stores*\n```\n{$rows}\n```");
        }

        $blocks[] = $this->section("*Pipeline*\n" . implode("\n", $this->pipelineLines($report)));

        if ($alerts = $this->alertLines($report)) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = $this->section("*⚠️ Needs attention*\n" . implode("\n", $alerts));
        }

        $blocks[] = [
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

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function pipelineLines(array $report): array
    {
        $lines = [];

        if ($report['webhooks']['available']) {
            $lines[] = $this->status(
                true,
                sprintf('%d webhook(s) synced', $report['webhooks']['synced'])
                . ($report['webhooks']['parked'] > 0
                    ? sprintf(', %d parked (store not linked)', $report['webhooks']['parked'])
                    : '')
            );
        }

        $lines[] = $this->status(
            $report['failures_opened'] === 0,
            $report['failures_opened'] === 0
                ? 'No sync failures'
                : sprintf('%d sync failure(s) opened today', $report['failures_opened'])
        );

        $lines[] = $this->status(
            $report['failed_jobs'] === 0 && $report['queue_depth'] < self::QUEUE_DEPTH_ALERT,
            sprintf('%d failed job(s), %d queued', $report['failed_jobs'], $report['queue_depth'])
        );

        $shipments = $report['shipments'];

        $lines[] = $this->status(
            true,
            $shipments->isEmpty()
                ? 'No shipments booked'
                : sprintf(
                    '%d shipment(s) booked — %s',
                    $shipments->sum(),
                    $shipments->map(fn (int $n, string $courier) => "{$courier} {$n}")->implode(', ')
                )
        );

        return $lines;
    }

    /**
     * Only thresholds that are actually crossed. A warning that prints every
     * morning is a warning nobody reads.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    private function alertLines(array $report): array
    {
        $alerts = [];

        if ($report['missing_city'] > 0) {
            $alerts[] = sprintf(
                '• *%d of %d orders have no shipping city* — the courier will route them wrong or reject them',
                $report['missing_city'],
                $report['orders']
            );
        }

        if ($report['failures_open'] > 0) {
            $alerts[] = sprintf('• *%d sync failure(s) still unresolved* — orders may be missing from the portal', $report['failures_open']);
        }

        if ($report['failed_jobs'] > 0) {
            $alerts[] = sprintf('• *%d job(s) in the failed queue* — `php artisan queue:retry all`', $report['failed_jobs']);
        }

        if ($report['queue_depth'] >= self::QUEUE_DEPTH_ALERT) {
            $alerts[] = sprintf('• *Queue is %d deep* — the worker is not keeping up', $report['queue_depth']);
        }

        if ($report['unclaimed'] > 0) {
            $alerts[] = sprintf('• *%d store(s) installed the app but never connected it* — their orders are being ignored', $report['unclaimed']);
        }

        if ($report['app_errors'] > 0) {
            $alerts[] = sprintf('• *%d application error(s) logged* — check `storage/logs`', $report['app_errors']);
        }

        if ($report['webhooks']['available'] && $report['webhooks']['synced'] === 0 && $report['orders'] > 0) {
            $alerts[] = '• *No webhooks synced all day* while orders still arrived — check the Shopify subscriptions';
        }

        return $alerts;
    }

    /**
     * Percentages read badly once a day is several times the norm, and a "560%"
     * increase is harder to place at a glance than "5.6×".
     */
    private function trend(float $value, float $baseline): string
    {
        if ($baseline <= 0.0) {
            return '';
        }

        $ratio = $value / $baseline;

        if ($ratio >= 2.0) {
            return sprintf('▲ %.1f× avg', $ratio);
        }

        $change = (int) round(($ratio - 1) * 100);

        return match (true) {
            $change >= 10 => "▲ {$change}% vs avg",
            $change <= -10 => '▼ ' . abs($change) . '% vs avg',
            default => '≈ avg',
        };
    }

    private function status(bool $ok, string $text): string
    {
        return ($ok ? '✅' : '⚠️') . ' ' . $text;
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
