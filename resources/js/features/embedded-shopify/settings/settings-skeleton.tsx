/**
 * React counterpart of the settings skeleton in resources/views/embedded.blade.php.
 * See dashboard-skeleton.tsx — same reasoning: the shell paints this before the
 * bundle runs, and React's first render must reproduce it so the mount is
 * invisible rather than a blank frame.
 */
export function SettingsSkeleton() {
    return (
        <div className="eb-skeleton">
            <h1 className="eb-skeleton-title">Sync Settings</h1>
            <p className="eb-skeleton-subtitle">
                Control which orders are processed by KSA Drop. By default,
                every order is processed.
            </p>
            <div className="eb-skeleton-section">
                <div className="eb-skeleton-card eb-skeleton-block" />
            </div>
            <div className="eb-skeleton-section">
                <div className="eb-skeleton-card eb-skeleton-block" />
            </div>
            <div className="eb-skeleton-section">
                <div className="eb-skeleton-card eb-skeleton-block" />
            </div>
        </div>
    );
}
