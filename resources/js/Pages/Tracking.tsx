// This page is no longer routed. All tracking goes through /track (TrackingSearch).
export default function Tracking() {
  if (typeof window !== 'undefined') {
    window.location.replace('/track')
  }
  return null
}
