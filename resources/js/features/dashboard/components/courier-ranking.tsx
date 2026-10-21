import { useId, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import {
    CheckCircle2,
    Crown,
    Package,
    RotateCcw,
    Timer,
    Truck,
} from "lucide-react";
import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";
import {
    IconImile,
    IconJnt,
    IconKsaExpress,
    IconLogesTechs,
} from "@/assets/brand-icons";
import type { CourierInsight } from "@/hooks/useDashboardInsights";
import { CountUp, InsightCard, InsightEmpty, percent } from "./insight-card";

const ACCENT = "var(--viz-good)";
const EASE = [0.16, 1, 0.3, 1] as const;

/**
 * Each courier in its brand colour, never by rank, so a new period never
 * repaints one. KSA Express is ink: black in light mode, white in dark.
 */
const COURIERS: Record<
    string,
    { label: string; color: string; logo: React.ReactNode }
> = {
    jnt_express: {
        label: "J&T Express",
        color: "var(--viz-jnt)",
        logo: <IconJnt />,
    },
    imile: { label: "iMile", color: "var(--viz-imile)", logo: <IconImile /> },
    logestechs: {
        label: "LogesTechs",
        color: "var(--viz-logestechs)",
        logo: <IconLogesTechs />,
    },
    ksadrop_express: {
        label: "KSA Express",
        color: "var(--viz-ksx)",
        logo: <IconKsaExpress />,
    },
};

const OUTCOMES = [
    { key: "delivered", label: "Delivered", color: "var(--viz-good)" },
    { key: "in_progress", label: "In progress", color: "var(--viz-pending)" },
    { key: "returned", label: "Returned", color: "var(--viz-bad)" },
] as const;

export function courierMeta(courier: string) {
    return (
        COURIERS[courier] ?? {
            label: courier,
            color: "var(--viz-pending)",
            logo: <Truck className="m-auto size-4" />,
        }
    );
}

export function formatDuration(hours: number | null) {
    if (hours === null) return "—";
    if (hours < 24) return `${Math.max(1, Math.round(hours))}h`;
    return `${(hours / 24).toFixed(1)}d`;
}

/** Ring geometry. */
const SIZE = 248;
const C = SIZE / 2;
const OUTER = 112;
const HOLE = 46;

/** An arc clockwise from twelve o'clock covering `frac` of the circle. */
function arc(r: number, frac: number) {
    const f = Math.min(Math.max(frac, 0.0001), 0.9999);
    const a = -Math.PI / 2 + f * Math.PI * 2;
    const x = C + r * Math.cos(a);
    const y = C + r * Math.sin(a);
    return `M${C},${C - r}A${r},${r} 0 ${f > 0.5 ? 1 : 0} 1 ${x.toFixed(2)},${y.toFixed(2)}`;
}

function arcEnd(r: number, frac: number): [number, number] {
    const a = -Math.PI / 2 + Math.min(frac, 0.9999) * Math.PI * 2;
    return [C + r * Math.cos(a), C + r * Math.sin(a)];
}

export function CourierRanking({
    couriers,
    loading,
}: {
    couriers: CourierInsight[] | null;
    loading: boolean;
}) {
    const [active, setActive] = useState<string | null>(null);
    const list = couriers ?? [];
    // New figures replay the sweep.
    const replayKey = list
        .map((c) => `${c.courier}:${c.total}:${c.delivered}`)
        .join("|");

    return (
        <InsightCard
            eyebrow="Delivery"
            title="Best couriers"
            description="Ranked by delivery rate: delivered out of all shipments"
            icon={<Truck />}
            accent={ACCENT}
        >
            {loading ? (
                <div className="grid items-center gap-6 sm:grid-cols-[248px_1fr]">
                    <Skeleton className="mx-auto size-[248px] rounded-full" />
                    <div className="space-y-3">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <Skeleton
                                key={i}
                                className="h-14 w-full rounded-xl"
                            />
                        ))}
                    </div>
                </div>
            ) : !list.length ? (
                <InsightEmpty>No shipments in this period.</InsightEmpty>
            ) : (
                <div className="flex flex-1 flex-col gap-6">
                    <div className="grid items-center gap-6 sm:grid-cols-[248px_1fr]">
                        <Rings
                            key={replayKey}
                            couriers={list}
                            active={active}
                            onActive={setActive}
                        />

                        <div key={`rows-${replayKey}`}>
                            <ol className="space-y-1.5">
                                {list.map((c, i) => (
                                    <CourierRow
                                        key={c.courier}
                                        courier={c}
                                        index={i}
                                        active={active}
                                        onActive={setActive}
                                    />
                                ))}
                            </ol>

                            <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 px-2.5 text-[10px] text-muted-foreground">
                                {OUTCOMES.map((o) => (
                                    <li
                                        key={o.key}
                                        className="flex items-center gap-1.5"
                                    >
                                        <span
                                            className="size-1.5 rounded-full"
                                            style={{ background: o.color }}
                                        />
                                        {o.label}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    <NetworkSummary
                        key={`summary-${replayKey}`}
                        couriers={list}
                    />
                </div>
            )}
        </InsightCard>
    );
}

/**
 * Concentric rings, best courier outermost. Each sweeps round to its delivery
 * rate over a faint full-circle track, so 100% is always visible as the goal.
 */
function Rings({
    couriers,
    active,
    onActive,
}: {
    couriers: CourierInsight[];
    active: string | null;
    onActive: (courier: string | null) => void;
}) {
    const uid = useId().replace(/:/g, "");
    const reduce = useReducedMotion() ?? false;
    const n = couriers.length;
    const gap = 5;
    const width = Math.min(15, (OUTER - HOLE - gap * (n - 1)) / n);
    const radiusOf = (i: number) => OUTER - width / 2 - i * (width + gap);

    const leader = couriers[0];
    const focus = couriers.find((c) => c.courier === active) ?? leader;
    const focusMeta = courierMeta(focus.courier);

    return (
        <div className="relative mx-auto size-[248px]">
            <svg
                viewBox={`0 0 ${SIZE} ${SIZE}`}
                className="size-full"
                onMouseLeave={() => onActive(null)}
            >
                <defs>
                    {couriers.map((c) => {
                        const color = courierMeta(c.courier).color;
                        return (
                            <linearGradient
                                key={c.courier}
                                id={`ring-${uid}-${c.courier}`}
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor={color}
                                    stopOpacity={0.55}
                                />
                                <stop offset="100%" stopColor={color} />
                            </linearGradient>
                        );
                    })}
                    <filter
                        id={`glow-${uid}`}
                        x="-50%"
                        y="-50%"
                        width="200%"
                        height="200%"
                    >
                        <feGaussianBlur stdDeviation="3" />
                    </filter>
                </defs>

                {/* Quarter marks: 25, 50 and 75%. */}
                {[0.25, 0.5, 0.75].map((q) => {
                    const a = -Math.PI / 2 + q * Math.PI * 2;
                    return (
                        <line
                            key={q}
                            x1={C + (HOLE - 4) * Math.cos(a)}
                            y1={C + (HOLE - 4) * Math.sin(a)}
                            x2={C + (OUTER + 4) * Math.cos(a)}
                            y2={C + (OUTER + 4) * Math.sin(a)}
                            className="stroke-border"
                            strokeDasharray="1 3"
                        />
                    );
                })}

                {couriers.map((c, i) => {
                    const r = radiusOf(i);
                    const frac = c.delivery_rate / 100;
                    const color = courierMeta(c.courier).color;
                    const dim = active !== null && active !== c.courier;
                    const [ex, ey] = arcEnd(r, frac);
                    const delay = reduce ? 0 : 0.2 + i * 0.14;

                    return (
                        <motion.g
                            key={c.courier}
                            animate={{ opacity: dim ? 0.28 : 1 }}
                            transition={{ duration: 0.12 }}
                            onMouseEnter={() => onActive(c.courier)}
                        >
                            {/* Track: the full circle, i.e. 100%. */}
                            <circle
                                cx={C}
                                cy={C}
                                r={r}
                                fill="none"
                                stroke={color}
                                strokeOpacity={0.12}
                                strokeWidth={width}
                            />

                            {frac > 0 && (
                                <>
                                    {/* A blurred copy under the arc for a soft glow. */}
                                    <motion.path
                                        d={arc(r, frac)}
                                        fill="none"
                                        stroke={color}
                                        strokeWidth={width}
                                        strokeLinecap="round"
                                        filter={`url(#glow-${uid})`}
                                        initial={{
                                            pathLength: reduce ? 1 : 0,
                                            opacity: 0,
                                        }}
                                        animate={{
                                            pathLength: 1,
                                            opacity:
                                                active === c.courier
                                                    ? 0.6
                                                    : 0.3,
                                        }}
                                        transition={{
                                            pathLength: {
                                                duration: 1.4,
                                                delay,
                                                ease: EASE,
                                            },
                                            opacity: { duration: 0.12 },
                                        }}
                                    />
                                    <motion.path
                                        d={arc(r, frac)}
                                        fill="none"
                                        stroke={`url(#ring-${uid}-${c.courier})`}
                                        strokeWidth={width}
                                        strokeLinecap="round"
                                        initial={{ pathLength: reduce ? 1 : 0 }}
                                        animate={{ pathLength: 1 }}
                                        transition={{
                                            duration: 1.4,
                                            delay,
                                            ease: EASE,
                                        }}
                                    />
                                    {/* The lit tip, arriving with the end of the sweep. */}
                                    <motion.circle
                                        cx={ex}
                                        cy={ey}
                                        r={Math.max(1.5, width / 2 - 2.5)}
                                        // A white tip would vanish on KSA Express's white ring.
                                        fill={
                                            c.courier === "ksadrop_express"
                                                ? "var(--card)"
                                                : "white"
                                        }
                                        initial={{ opacity: 0 }}
                                        animate={{ opacity: 0.9 }}
                                        transition={{
                                            duration: 0.3,
                                            delay: reduce ? 0 : delay + 1.1,
                                        }}
                                    />
                                </>
                            )}

                            {/* Hover target the full width of the ring. */}
                            <circle
                                cx={C}
                                cy={C}
                                r={r}
                                fill="none"
                                stroke="transparent"
                                strokeWidth={width + gap}
                            />
                        </motion.g>
                    );
                })}
            </svg>

            {/* Readout in the middle: the leader, or the courier under the pointer. */}
            <div className="pointer-events-none absolute inset-0 grid place-items-center">
                <AnimatePresence mode="wait">
                    <motion.div
                        key={focus.courier}
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.9 }}
                        transition={{ duration: 0.12, ease: EASE }}
                        className="flex w-[84px] flex-col items-center text-center"
                    >
                        <div className="mb-1 size-7 overflow-hidden rounded-lg border bg-white shadow-sm">
                            {focusMeta.logo}
                        </div>
                        <div className="text-xl leading-none font-bold tracking-tight tabular-nums">
                            <CountUp
                                value={focus.delivery_rate}
                                format={(v) => `${Math.round(v)}%`}
                            />
                        </div>
                        <div className="mt-0.5 flex max-w-full items-center gap-0.5 text-[9px] font-medium text-muted-foreground">
                            {focus === leader && (
                                <Crown
                                    className="size-2.5 shrink-0 text-viz-gold"
                                    strokeWidth={2.5}
                                />
                            )}
                            <span className="truncate">{focusMeta.label}</span>
                        </div>
                    </motion.div>
                </AnimatePresence>
            </div>
        </div>
    );
}

function CourierRow({
    courier: c,
    index,
    active,
    onActive,
}: {
    courier: CourierInsight;
    index: number;
    active: string | null;
    onActive: (courier: string | null) => void;
}) {
    const reduce = useReducedMotion() ?? false;
    const meta = courierMeta(c.courier);
    const isActive = active === c.courier;
    const delay = reduce ? 0 : 0.35 + index * 0.1;
    const [entered, setEntered] = useState(reduce);

    return (
        <motion.li
            initial={{ opacity: 0, x: 12 }}
            animate={{ opacity: active !== null && !isActive ? 0.5 : 1, x: 0 }}
            // The entrance is slow and staggered; hovering after it is instant.
            transition={
                entered
                    ? { duration: 0.12 }
                    : { duration: 0.5, delay, ease: EASE }
            }
            onAnimationComplete={() => setEntered(true)}
            onMouseEnter={() => onActive(c.courier)}
            onMouseLeave={() => onActive(null)}
            className={cn(
                "relative grid grid-cols-[auto_1fr_auto] items-center gap-x-3 overflow-hidden rounded-xl px-2.5 py-2 transition-colors duration-100",
                isActive ? "bg-muted" : "hover:bg-muted/50",
            )}
        >
            <div className="relative">
                <div className="size-9 overflow-hidden rounded-lg border bg-white shadow-sm">
                    {meta.logo}
                </div>
                {index === 0 && (
                    <span className="absolute -top-1.5 -right-1.5 grid size-4 place-items-center rounded-full bg-viz-gold ring-2 ring-card">
                        <Crown
                            className="size-2.5 text-white"
                            strokeWidth={3}
                        />
                    </span>
                )}
            </div>

            <div className="min-w-0">
                <div className="flex items-baseline gap-2">
                    <span className="truncate text-[13px] font-semibold">
                        {meta.label}
                    </span>
                    <span className="shrink-0 text-[10px] text-muted-foreground tabular-nums">
                        {c.total.toLocaleString()} ·{" "}
                        {formatDuration(c.avg_delivery_hours)} avg
                    </span>
                </div>

                {/* Delivered / in progress / returned, growing in from the left. */}
                <div className="mt-1.5 flex h-1.5 gap-[2px] overflow-hidden rounded-full bg-muted">
                    {OUTCOMES.map((o, k) => {
                        const value = c[o.key];
                        if (!value) return null;
                        return (
                            <motion.div
                                key={o.key}
                                title={`${o.label}: ${value.toLocaleString()} (${percent(value, c.total)})`}
                                className="h-full"
                                style={{ background: o.color }}
                                initial={{
                                    width: reduce
                                        ? `${(value / c.total) * 100}%`
                                        : "0%",
                                }}
                                animate={{
                                    width: `${(value / c.total) * 100}%`,
                                }}
                                transition={{
                                    duration: 1,
                                    delay: delay + 0.2 + k * 0.08,
                                    ease: EASE,
                                }}
                            />
                        );
                    })}
                </div>
            </div>

            <div className="text-right">
                <div className="text-sm leading-tight font-bold tabular-nums">
                    <CountUp
                        value={c.delivery_rate}
                        format={(v) => `${Math.round(v)}%`}
                    />
                </div>
                <div className="text-[10px] text-muted-foreground">
                    delivered
                </div>
            </div>
        </motion.li>
    );
}

/**
 * Every courier together: what the network as a whole delivers. Pinned to the
 * bottom of the card so it lines up with the chart beside it.
 */
function NetworkSummary({ couriers }: { couriers: CourierInsight[] }) {
    const reduce = useReducedMotion() ?? false;
    const total = couriers.reduce((s, c) => s + c.total, 0);
    const delivered = couriers.reduce((s, c) => s + c.delivered, 0);
    const returned = couriers.reduce((s, c) => s + c.returned, 0);

    // Average delivery time across the network, weighted by parcels delivered.
    const timed = couriers.filter(
        (c) => c.avg_delivery_hours !== null && c.delivered > 0,
    );
    const timedCount = timed.reduce((s, c) => s + c.delivered, 0);
    const avgHours = timedCount
        ? timed.reduce((s, c) => s + c.avg_delivery_hours! * c.delivered, 0) /
          timedCount
        : null;
    const fastest = [...timed].sort(
        (a, b) => a.avg_delivery_hours! - b.avg_delivery_hours!,
    )[0];

    const tiles = [
        {
            icon: <Package />,
            label: "Shipments",
            value: <CountUp value={total} />,
            sub: `across ${couriers.length} ${couriers.length === 1 ? "courier" : "couriers"}`,
            bar: null,
        },
        {
            icon: <CheckCircle2 />,
            label: "Delivered",
            value: (
                <CountUp
                    value={total ? (delivered / total) * 100 : 0}
                    format={(v) => `${Math.round(v)}%`}
                />
            ),
            sub: `${delivered.toLocaleString()} parcels`,
            bar: {
                value: total ? delivered / total : 0,
                color: "var(--viz-good)",
            },
        },
        {
            icon: <RotateCcw />,
            label: "Returned",
            value: (
                <CountUp
                    value={total ? (returned / total) * 100 : 0}
                    format={(v) => `${Math.round(v)}%`}
                />
            ),
            sub: `${returned.toLocaleString()} parcels`,
            bar: {
                value: total ? returned / total : 0,
                color: "var(--viz-bad)",
            },
        },
        {
            icon: <Timer />,
            label: "Avg delivery",
            value: formatDuration(avgHours),
            sub: fastest
                ? `fastest: ${courierMeta(fastest.courier).label}`
                : "no deliveries yet",
            bar: null,
        },
    ];

    return (
        <div className="mt-auto">
            <div className="mb-2 flex items-center gap-2 px-0.5">
                <h4 className="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                    All couriers
                </h4>
                <span className="h-px flex-1 bg-border" />
            </div>
            <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                {tiles.map((t, i) => (
                    <motion.div
                        key={t.label}
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                            duration: 0.5,
                            delay: reduce ? 0 : 0.8 + i * 0.08,
                            ease: EASE,
                        }}
                        className="rounded-xl border bg-background/50 px-3 py-2.5"
                    >
                        <div className="flex items-center gap-1.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase [&_svg]:size-3">
                            {t.icon}
                            {t.label}
                        </div>
                        <div className="mt-0.5 text-lg leading-tight font-semibold tracking-tight tabular-nums">
                            {t.value}
                        </div>
                        {t.bar ? (
                            <div className="mt-1.5 h-1 overflow-hidden rounded-full bg-muted">
                                <motion.div
                                    className="h-full rounded-full"
                                    style={{ background: t.bar.color }}
                                    initial={{
                                        width: reduce
                                            ? `${t.bar.value * 100}%`
                                            : "0%",
                                    }}
                                    animate={{ width: `${t.bar.value * 100}%` }}
                                    transition={{
                                        duration: 1,
                                        delay: reduce ? 0 : 1 + i * 0.08,
                                        ease: EASE,
                                    }}
                                />
                            </div>
                        ) : null}
                        <div
                            className={cn(
                                "truncate text-[10px] text-muted-foreground",
                                t.bar ? "mt-1" : "mt-0.5",
                            )}
                        >
                            {t.sub}
                        </div>
                    </motion.div>
                ))}
            </div>
        </div>
    );
}
