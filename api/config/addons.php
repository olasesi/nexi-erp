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
|   module               - permission module, seeded as `<module>.<action>`
|   actions              - the CRUD actions this add-on actually routes, so no
|                          unreachable permission is ever seeded
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
];
