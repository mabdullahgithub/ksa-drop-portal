<?php

/**
 * The partner program's defaults. Live values are in the partner_settings
 * table (App\Models\PartnerSetting), editable from the admin Partners page;
 * these seed that table and are the fallback for a key that was never saved.
 */
return [
    // Points per event. One point is treated as one SAR when a payout is
    // valued; what points convert to is decided at payout time, by a person.
    'approval_bonus_points' => (int) env('PARTNERS_APPROVAL_BONUS', 50),
    'activation_points' => (int) env('PARTNERS_ACTIVATION_POINTS', 150),
    'delivery_points' => (int) env('PARTNERS_DELIVERY_POINTS', 1),
    // Delivered shipments earn points for this long after the referred
    // client's first delivery.
    'delivery_months' => (int) env('PARTNERS_DELIVERY_MONTHS', 12),
    'milestone_deliveries' => (int) env('PARTNERS_MILESTONE_DELIVERIES', 100),
    'milestone_points' => (int) env('PARTNERS_MILESTONE_POINTS', 200),

    // Tier by number of activated referred clients, and the multiplier each
    // tier applies to per-delivery points.
    'tiers' => [
        'bronze' => ['min_active_clients' => 0, 'multiplier' => 1.0],
        'silver' => ['min_active_clients' => 3, 'multiplier' => 1.25],
        'gold' => ['min_active_clients' => 10, 'multiplier' => 1.5],
    ],

    'min_redeem_points' => (int) env('PARTNERS_MIN_REDEEM', 500),
    'cookie_days' => (int) env('PARTNERS_COOKIE_DAYS', 90),
    'terms_version' => env('PARTNERS_TERMS_VERSION', '2026-11'),
];
