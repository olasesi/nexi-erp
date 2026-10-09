<?php

/*
|--------------------------------------------------------------------------
| Add-ons
|--------------------------------------------------------------------------
| The installable add-on catalog. Each entry is a self-contained feature
| bundle: routes gated by `addon:<id>`, permissions seeded from `module` +
| `actions`, and an on/off toggle per company persisted in the `addons`
| business settings group (see config/business-settings.php).
|
| Registering a new add-on means adding an entry here, its routes, its
| migration and its tests. The catalog listing, per-company toggles, route
| gating and sidebar hiding all read from this file.
|
| Fields:
|   label                - display name
|   description          - one line shown in the catalog
|   category             - catalog grouping
|   status               - free | premium | coming_soon
|   module               - primary permission module, seeded as `<module>.<action>`
|   actions              - the CRUD actions this add-on actually routes, so no
|                          unreachable permission is ever seeded
|   modules              - optional extra permission modules for add-ons that
|                          route more than one resource (e.g. bookings). Each
|                          entry maps its own `module` to the `actions` it
|                          routes; missing, falls back to `module` + `actions`
|   enabled_by_default   - toggle state for a company that has never chosen
*/

return [
    'notice-board' => [
        'label' => 'Notice Board',
        'description' => 'Publish company-wide announcements with priority, publish and expiry dates.',
        'category' => 'Communication and Customization',
        'status' => 'free',
        'module' => 'notices',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'suggestion-box' => [
        'label' => 'Suggestion Box',
        'description' => 'Collect ideas and improvement requests from staff, then review them.',
        'category' => 'Communication and Customization',
        'status' => 'free',
        'module' => 'suggestions',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'business-advisor' => [
        'label' => 'AI Business Advisor',
        'description' => 'Health score by category, 7-day trend, alerts, insights and recommendations.',
        'category' => 'AI',
        'status' => 'free',
        'module' => 'business-advisor',
        'actions' => ['view-any', 'view'],
        'enabled_by_default' => true,
    ],

    'smart-dashboard' => [
        'label' => 'Smart Dashboard',
        'description' => 'Executive, financial, sales and operations analytics in four tabs.',
        'category' => 'Analytics',
        'status' => 'free',
        'module' => 'analytics',
        'actions' => ['view'],
        'enabled_by_default' => true,
    ],

    // ---- Vertical solutions. Each one is a genuine domain bundle (storage,
    // lifecycle, capacity or commitment invariants) routed as add-ons so a
    // company only carries the modules it actually runs.
    'coworking-space' => [
        'label' => 'Coworking Space',
        'description' => 'Book desks, private offices and meeting rooms with capacity and double-booking checks.',
        'category' => 'Vertical Solutions',
        'status' => 'free',
        'module' => 'coworking-units',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'coworking-units', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'coworking-bookings', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'laundry-dry-cleaning' => [
        'label' => 'Laundry & Dry Cleaning',
        'description' => 'Move garments through washing, drying and folding to pickup, with per-item pricing.',
        'category' => 'Vertical Solutions',
        'status' => 'free',
        'module' => 'laundry-orders',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'laundry-orders', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'laundry-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'gym-management' => [
        'label' => 'Gym Management',
        'description' => 'Membership plans, visits and daily quota enforcement for a fitness club.',
        'category' => 'Vertical Solutions',
        'status' => 'free',
        'module' => 'gym-plans',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'gym-plans', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'gym-members', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'gym-visits', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'rental' => [
        'label' => 'Rental Management',
        'description' => 'Itemised rental agreements with partial returns, overdue tracking and late fees.',
        'category' => 'Vertical Solutions',
        'status' => 'free',
        'module' => 'rental-agreements',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'rental-agreements', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'rental-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],
];
