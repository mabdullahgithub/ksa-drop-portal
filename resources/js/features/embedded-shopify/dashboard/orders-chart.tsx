import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'
import type { DailyOrders } from '../api-client'

/**
 * Single-series orders-per-day trend (last 30 days). One hue (validated
 * palette blue), 2px line, ~10% area wash, hairline solid grid, hover
 * crosshair + tooltip. No legend — the section title names the series.
 */
export function OrdersChart({ data }: { data: DailyOrders[] }) {
    return (
        <div className="orders-chart">
            <style>{`
                .orders-chart {
                    --series-1: #2a78d6;
                    --chart-surface: #fcfcfb;
                    --chart-grid: #e1e0d9;
                    --chart-axis-ink: #898781;
                    --chart-ink: #0b0b0b;
                    --chart-ink-2: #52514e;
                    height: 240px;
                }
                @media (prefers-color-scheme: dark) {
                    .orders-chart {
                        --series-1: #3987e5;
                        --chart-surface: #1a1a19;
                        --chart-grid: #2c2c2a;
                        --chart-ink: #ffffff;
                        --chart-ink-2: #c3c2b7;
                    }
                }
                .orders-chart-tooltip {
                    background: var(--chart-surface);
                    border: 1px solid var(--chart-grid);
                    border-radius: 8px;
                    padding: 8px 10px;
                    font-size: 12px;
                    color: var(--chart-ink-2);
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
                }
                .orders-chart-tooltip strong {
                    display: block;
                    color: var(--chart-ink);
                    font-size: 14px;
                }
            `}</style>
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
                    <CartesianGrid stroke="var(--chart-grid)" strokeWidth={1} vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickFormatter={formatDay}
                        tick={{ fill: 'var(--chart-axis-ink)', fontSize: 11 }}
                        tickLine={false}
                        axisLine={{ stroke: 'var(--chart-grid)' }}
                        interval="preserveStartEnd"
                        minTickGap={32}
                    />
                    <YAxis
                        allowDecimals={false}
                        tick={{ fill: 'var(--chart-axis-ink)', fontSize: 11 }}
                        tickLine={false}
                        axisLine={false}
                        width={40}
                    />
                    <Tooltip
                        cursor={{ stroke: 'var(--chart-axis-ink)', strokeWidth: 1 }}
                        content={({ active, payload }) => {
                            if (!active || !payload?.length) return null
                            const point = payload[0].payload as DailyOrders
                            return (
                                <div className="orders-chart-tooltip">
                                    <strong>
                                        {point.orders} {point.orders === 1 ? 'order' : 'orders'}
                                    </strong>
                                    {formatDayLong(point.date)}
                                </div>
                            )
                        }}
                    />
                    <Area
                        type="monotone"
                        dataKey="orders"
                        stroke="var(--series-1)"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                        fill="var(--series-1)"
                        fillOpacity={0.1}
                        activeDot={{
                            r: 4,
                            fill: 'var(--series-1)',
                            stroke: 'var(--chart-surface)',
                            strokeWidth: 2,
                        }}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    )
}

function formatDay(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    })
}

function formatDayLong(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    })
}
