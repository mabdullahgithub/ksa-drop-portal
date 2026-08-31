/**
 * React counterpart of the static skeleton in resources/views/embedded.blade.php.
 *
 * The shell paints that markup before this bundle even executes; React's first
 * render then clears the container, so the loading state has to render the same
 * thing or the page flashes blank at mount. Same class names, same CSS (defined
 * once, in the shell) — and plain elements rather than <s-*>, so it doesn't wait
 * on the Polaris custom elements to upgrade.
 */
export function DashboardSkeleton() {
    return (
        <div className="eb-skeleton">
            <h1 className="eb-skeleton-title">Order Sync</h1>

            <div className="eb-skeleton-section">
                <h2 className="eb-skeleton-heading">Overview</h2>
                <div className="eb-skeleton-grid">
                    <SkeletonCard label="Total orders synced" />
                    <SkeletonCard label="Processed" />
                    <SkeletonCard label="Pending review" />
                    <SkeletonCard label="Skipped by filter" />
                </div>
            </div>

            <div className="eb-skeleton-section">
                <h2 className="eb-skeleton-heading">
                    Orders per day (last 30 days)
                </h2>
                <div className="eb-skeleton-chart" />
            </div>

            <div className="eb-skeleton-section">
                <h2 className="eb-skeleton-heading">Recent orders</h2>
                <div className="eb-skeleton-table" />
            </div>
        </div>
    );
}

function SkeletonCard({ label }: { label: string }) {
    return (
        <div className="eb-skeleton-card">
            <span className="eb-skeleton-label">{label}</span>
            <span className="eb-skeleton-value">—</span>
        </div>
    );
}
