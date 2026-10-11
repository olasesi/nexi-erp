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

    // ---- Cross-domain tools. Unlike the vertical solutions these are not
    // tied to one industry: any company can switch on the panels it needs.
    'form-builder' => [
        'label' => 'Form Builder',
        'description' => 'Build custom forms with typed fields, publish them and collect structured submissions.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'forms',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'forms', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'form-fields', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'form-submissions', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'messenger' => [
        'label' => 'Messenger',
        'description' => 'Unified inbox for internal, WhatsApp, Facebook, Instagram and email conversations.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'conversations',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'conversations', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'messages', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'document-manager' => [
        'label' => 'Document Manager',
        'description' => 'Folder tree, file metadata, versions and per-user sharing for company documents.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'document-folders',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'document-folders', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'documents', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'support-tickets' => [
        'label' => 'Support Tickets',
        'description' => 'Ticket lifecycle from open to resolved with threaded public and internal replies.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'tickets',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'tickets', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'ticket-replies', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'calendar-events' => [
        'label' => 'Calendar & Events',
        'description' => 'Schedule meetings, holidays and appointments with invited attendees and RSVP.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'events',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'events', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'event-attendees', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'asset-management' => [
        'label' => 'Asset Management',
        'description' => 'Track company assets by category, assignment and return over their lifecycle.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'assets',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'asset-categories', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'assets', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'asset-assignments', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    // ---- Phase 1 analytics and tools.
    'smart-reports' => [
        'label' => 'Smart Reports',
        'description' => 'Saved report definitions over your live data with columns, filters and grouping.',
        'category' => 'Analytics',
        'status' => 'free',
        'module' => 'smart-reports',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'ai-agent' => [
        'label' => 'AI Agent',
        'description' => 'Conversational assistant with reusable prompts, thread history and a provider-agnostic driver.',
        'category' => 'AI',
        'status' => 'free',
        'module' => 'ai-agent-threads',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'ai-agent-threads', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'ai-agent-messages', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'ai-agent-prompts', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'goals' => [
        'label' => 'Goal',
        'description' => 'Objectives and key results with progress tracking and a completion lifecycle.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'goals',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'goals', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'goal-key-results', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'budget-planner' => [
        'label' => 'Budget Planner',
        'description' => 'Plan income and expense lines by period and track planned versus actual spend.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'budgets',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'budgets', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'budget-lines', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'training' => [
        'label' => 'Training',
        'description' => 'Schedule internal trainings by type, trainer, branch and lifecycle status.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'trainings',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'trainings', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'training-types', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'youtube' => [
        'label' => 'YouTube',
        'description' => 'Manage channel, videos, playlists and activity; stays dormant until the channel is connected.',
        'category' => 'Tools',
        'status' => 'free',
        'module' => 'youtube-videos',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'youtube-videos', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'youtube-playlists', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'youtube-channel', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'youtube-activity', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    // ---- Phase 2: the HRM suite. Each add-on is a slice of staffing
    // operations — performance, hiring, scheduling, time and compliance.
    'performance' => [
        'label' => 'Performance',
        'description' => 'Employee performance reviews with scored ratings and an approve/reopen lifecycle.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'performance-reviews',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'performance-indicator' => [
        'label' => 'Performance Indicator',
        'description' => 'Catalog of weighted performance indicators used to score reviews.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'performance-indicators',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'recruitment' => [
        'label' => 'Recruitment',
        'description' => 'Job postings and the candidate pipeline from application to hire.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'job-postings',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'job-postings', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'job-candidates', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'job-search' => [
        'label' => 'Job Search',
        'description' => 'Candidate-facing job board listing open postings and collecting applications.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'job-openings',
        'actions' => ['view-any', 'view'],
        'modules' => [
            ['module' => 'job-openings', 'actions' => ['view-any', 'view']],
            ['module' => 'job-applications', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'work-permit' => [
        'label' => 'Work Permit',
        'description' => 'Track employee work permits, issuing country, expiry and renewals.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'work-permits',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'rotas' => [
        'label' => 'Rotas',
        'description' => 'Build weekly shift rotas and assign staff to dated shifts.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'rotas',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'rotas', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'rota-entries', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'commission' => [
        'label' => 'Commission',
        'description' => 'Sales commissions per employee with an approve and paid lifecycle.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'commissions',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'time-tracker' => [
        'label' => 'Time Tracker',
        'description' => 'Start/stop time entries against work, with billable flags and durations.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'time-entries',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'timesheet' => [
        'label' => 'Timesheet',
        'description' => 'Period timesheets with line entries and a submit/approve/reject workflow.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'timesheets',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'timesheets', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'timesheet-entries', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'team-workload' => [
        'label' => 'Team Workload',
        'description' => 'Read-only view of each employee’s active work and logged time.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'team-workload',
        'actions' => ['view-any', 'view'],
        'enabled_by_default' => true,
    ],

    'digital-certificate' => [
        'label' => 'Digital Certificate',
        'description' => 'Issue, track and revoke employee certificates and credentials.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'digital-certificates',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    // Phase 3 broadens sales and CRM beyond the order-to-invoice core:
    // proposals, targets, returns, adjustment notes, referral agents and a
    // couple of product/customer catalog helpers.
    'proposal' => [
        'label' => 'Proposal',
        'description' => 'Client proposals with line items that convert into an invoice once accepted.',
        'category' => 'Sales',
        'status' => 'free',
        'module' => 'proposals',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'proposals', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'proposal-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'sales-target' => [
        'label' => 'Sales Target',
        'description' => 'Per-rep sales targets with achieved-to-date progress tracking.',
        'category' => 'Sales',
        'status' => 'free',
        'module' => 'sales-targets',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'sales-return' => [
        'label' => 'Sales Return',
        'description' => 'Customer returns with line items and a completion workflow.',
        'category' => 'Sales',
        'status' => 'free',
        'module' => 'sales-returns',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'sales-returns', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'sales-return-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'credit-note' => [
        'label' => 'Credit Note',
        'description' => 'Credit notes issued against sales returns, with approve and apply lifecycle.',
        'category' => 'Sales',
        'status' => 'free',
        'module' => 'credit-notes',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'credit-notes', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'credit-note-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'debit-note' => [
        'label' => 'Debit Note',
        'description' => 'Debit notes raised on vendors, with approve and apply lifecycle.',
        'category' => 'Sales',
        'status' => 'free',
        'module' => 'debit-notes',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'debit-notes', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'debit-note-items', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'sales-agent' => [
        'label' => 'Sales Agent',
        'description' => 'Referral agents onboarded by request, with the agent order pipeline.',
        'category' => 'CRM',
        'status' => 'free',
        'module' => 'sales-agents',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'sales-agents', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'sales-agent-orders', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'customer-group' => [
        'label' => 'Customer Group',
        'description' => 'Customer segments carrying a shared discount rate.',
        'category' => 'CRM',
        'status' => 'free',
        'module' => 'customer-groups',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'product-unit' => [
        'label' => 'Product Unit',
        'description' => 'Units of measure used across products and order lines.',
        'category' => 'Products',
        'status' => 'free',
        'module' => 'product-units',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'deal-activity' => [
        'label' => 'Deal Activity',
        'description' => 'Log calls, meetings and notes against deals and mark them complete.',
        'category' => 'CRM',
        'status' => 'free',
        'module' => 'deal-activities',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'announcement' => [
        'label' => 'Announcement',
        'description' => 'Company-wide announcements with audience targeting and a publish/archive lifecycle.',
        'category' => 'Collaboration',
        'status' => 'free',
        'module' => 'announcements',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'todo' => [
        'label' => 'To-Do',
        'description' => 'Personal and team to-dos with priority, due dates and completion.',
        'category' => 'Collaboration',
        'status' => 'free',
        'module' => 'todos',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'knowledge-base' => [
        'label' => 'Knowledge Base',
        'description' => 'Categorised help articles with draft/published lifecycle.',
        'category' => 'Collaboration',
        'status' => 'free',
        'module' => 'kb-categories',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'modules' => [
            ['module' => 'kb-categories', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
            ['module' => 'kb-articles', 'actions' => ['view-any', 'view', 'create', 'update', 'delete']],
        ],
        'enabled_by_default' => true,
    ],

    'note' => [
        'label' => 'Note',
        'description' => 'Quick personal notes with pinning.',
        'category' => 'Collaboration',
        'status' => 'free',
        'module' => 'notes',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'award' => [
        'label' => 'Award',
        'description' => 'Recognise employees with awards, gifts and awarded dates.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'awards',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],

    'company-policy' => [
        'label' => 'Company Policy',
        'description' => 'Versioned company policies with a publish/archive lifecycle.',
        'category' => 'HRM',
        'status' => 'free',
        'module' => 'company-policies',
        'actions' => ['view-any', 'view', 'create', 'update', 'delete'],
        'enabled_by_default' => true,
    ],
];
