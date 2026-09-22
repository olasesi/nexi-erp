<?php

use Illuminate\Validation\Rule;

$languages = [
    'en' => 'English',
    'es' => 'Español',
    'zh' => '中文',
    'hi' => 'हिन्दी',
    'ar' => 'العربية',
    'fr' => 'Français',
    'pt' => 'Português',
    'ru' => 'Русский',
    'id' => 'Bahasa Indonesia',
    'de' => 'Deutsch',
    'ja' => '日本語',
    'tr' => 'Türkçe',
    'vi' => 'Tiếng Việt',
    'ko' => '한국어',
    'it' => 'Italiano',
];

$themes = ['light', 'twilight', 'dark'];

$themeColors = ['green', 'blue', 'red', 'orange', 'yellow', 'pink', 'purple', 'sky', 'gray', 'black', 'cyan', 'indigo', 'teal'];

$dashboardWidgets = [
    'total_revenue' => ['label' => 'Total Revenue', 'type' => 'kpi', 'size' => ['w' => 3, 'h' => 1]],
    'total_orders' => ['label' => 'Total Orders', 'type' => 'kpi', 'size' => ['w' => 3, 'h' => 1]],
    'total_customers' => ['label' => 'Total Customers', 'type' => 'kpi', 'size' => ['w' => 3, 'h' => 1]],
    'pending_orders' => ['label' => 'Pending Orders', 'type' => 'kpi', 'size' => ['w' => 3, 'h' => 1]],
    'low_stock' => ['label' => 'Low Stock Alerts', 'type' => 'kpi', 'size' => ['w' => 3, 'h' => 1]],
    'sales_trend' => ['label' => 'Sales Trend', 'type' => 'chart', 'size' => ['w' => 6, 'h' => 2]],
    'top_products' => ['label' => 'Top Products', 'type' => 'chart', 'size' => ['w' => 6, 'h' => 2]],
    'recent_orders' => ['label' => 'Recent Orders', 'type' => 'list', 'size' => ['w' => 6, 'h' => 2]],
    'stock_alerts' => ['label' => 'Stock Alerts', 'type' => 'list', 'size' => ['w' => 6, 'h' => 2]],
    'recent_activity' => ['label' => 'Recent Activity', 'type' => 'list', 'size' => ['w' => 6, 'h' => 2]],
];

$dashboardLayouts = [
    'minimal' => [
        'label' => 'Minimalist',
        'description' => 'A clean, focused view with only the essential numbers and one chart.',
        'widgets' => ['total_revenue', 'total_orders', 'total_customers', 'sales_trend'],
    ],
    'maximal' => [
        'label' => 'Maximalist',
        'description' => 'Every widget on screen, organised in a dense information grid.',
        'widgets' => ['total_revenue', 'total_orders', 'total_customers', 'pending_orders', 'low_stock', 'sales_trend', 'top_products', 'recent_orders', 'stock_alerts', 'recent_activity'],
    ],
    'executive' => [
        'label' => 'Executive',
        'description' => 'A business summary with key KPIs and a prominent revenue chart.',
        'widgets' => ['total_revenue', 'total_orders', 'sales_trend', 'recent_orders'],
    ],
    'analytics' => [
        'label' => 'Analytics',
        'description' => 'Chart-first layout for performance analysis and trends.',
        'widgets' => ['sales_trend', 'top_products', 'total_revenue', 'recent_orders'],
    ],
    'operations' => [
        'label' => 'Operations',
        'description' => 'Order and stock focused view for daily operations teams.',
        'widgets' => ['pending_orders', 'low_stock', 'stock_alerts', 'recent_orders', 'total_orders'],
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Settings groups
    |--------------------------------------------------------------------------
    | Mirrors the WorkDo Dash settings surface: each group maps to a settings
    | tab. Values not present in the database fall back to the defined
    | defaults below.
    |--------------------------------------------------------------------------
    */

    'groups' => [
        'brand' => [
            'label' => 'Brand Settings',
            'keys' => [
                'logo_light' => ['nullable', 'string', 'max:255'],
                'logo_dark' => ['nullable', 'string', 'max:255'],
                'favicon' => ['nullable', 'string', 'max:255'],
                'titleText' => ['nullable', 'string', 'max:100'],
                'footerText' => ['nullable', 'string', 'max:255'],
                'sidebarVariant' => ['nullable', 'string', Rule::in(['one-page', 'spaced'])],
                'sidebarStyle' => ['nullable', 'string', 'max:20'],
                'layoutDirection' => ['nullable', 'string', Rule::in(['ltr', 'rtl'])],
                'themeMode' => ['nullable', 'string', Rule::in($themes)],
                'themeColor' => ['nullable', 'string', Rule::in($themeColors)],
                'customColor' => ['nullable', 'string', 'max:20'],
            ],
            'defaults' => [
                'logo_light' => null,
                'logo_dark' => null,
                'favicon' => null,
                'titleText' => 'Nexi ERP',
                'footerText' => 'Copyright © Nexi ERP',
                'sidebarVariant' => 'spaced',
                'sidebarStyle' => 'v6',
                'layoutDirection' => 'ltr',
                'themeMode' => 'light',
                'themeColor' => 'green',
                'customColor' => null,
            ],
        ],

        'dashboard' => [
            'label' => 'Dashboard Settings',
            'keys' => [
                'layout' => ['nullable', 'string', 'max:20', Rule::in(array_keys($dashboardLayouts))],
                'density' => ['nullable', 'string', Rule::in(['comfortable', 'compact'])],
            ],
            'defaults' => [
                'layout' => 'minimal',
                'density' => 'comfortable',
            ],
        ],

        'system' => [
            'label' => 'System Settings',
            'keys' => [
                'defaultLanguage' => ['nullable', 'string', 'max:10', Rule::in(array_keys($languages))],
                'dateFormat' => ['nullable', 'string', 'max:20'],
                'timeFormat' => ['nullable', 'string', 'max:20'],
                'calendarStartDay' => ['nullable', 'integer', 'between:1,7'],
                'enableRegistration' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'enableEmailVerification' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'landingPageEnabled' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'termsConditionsUrl' => ['nullable', 'url'],
            ],
            'defaults' => [
                'defaultLanguage' => 'en',
                'dateFormat' => 'Y-m-d',
                'timeFormat' => 'H:i',
                'calendarStartDay' => '1',
                'enableRegistration' => 'on',
                'enableEmailVerification' => 'off',
                'landingPageEnabled' => 'on',
                'termsConditionsUrl' => null,
            ],
        ],

        'currency' => [
            'label' => 'Currency Settings',
            'keys' => [
                'defaultCurrency' => ['nullable', 'string', 'max:10'],
                'currency_format' => ['nullable', 'integer', 'between:0,3'],
                'decimalFormat' => ['nullable', 'integer', 'between:0,3'],
                'decimalSeparator' => ['nullable', 'string', 'max:5'],
                'thousandsSeparator' => ['nullable', 'string', 'max:5'],
                'floatNumber' => ['nullable', 'integer', 'between:0,8'],
                'currencySymbolSpace' => ['nullable', 'string', 'max:5'],
                'currencySymbolPosition' => ['nullable', 'string', Rule::in(['left', 'right'])],
            ],
            'defaults' => [
                'defaultCurrency' => 'USD',
                'currency_format' => '2',
                'decimalFormat' => '2',
                'decimalSeparator' => '.',
                'thousandsSeparator' => ',',
                'floatNumber' => '2',
                'currencySymbolSpace' => '',
                'currencySymbolPosition' => 'left',
            ],
        ],

        'seo' => [
            'label' => 'SEO Settings',
            'keys' => [
                'metaTitle' => ['nullable', 'string', 'max:255'],
                'metaKeywords' => ['nullable', 'string', 'max:255'],
                'metaDescription' => ['nullable', 'string', 'max:255'],
                'metaImage' => ['nullable', 'string', 'max:255'],
            ],
            'defaults' => [
                'metaTitle' => 'Nexi ERP - Dashboard',
                'metaKeywords' => '',
                'metaDescription' => '',
                'metaImage' => null,
            ],
        ],

        'cookie' => [
            'label' => 'Cookie Settings',
            'keys' => [
                'enableCookiePopup' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'enableLogging' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'strictlyNecessaryCookies' => ['nullable', 'string', Rule::in(['on', 'off', '1', '0'])],
                'cookieTitle' => ['nullable', 'string', 'max:100'],
                'strictlyCookieTitle' => ['nullable', 'string', 'max:100'],
                'cookieDescription' => ['nullable', 'string'],
                'strictlyCookieDescription' => ['nullable', 'string'],
            ],
            'defaults' => [
                'enableCookiePopup' => '0',
                'enableLogging' => '0',
                'strictlyNecessaryCookies' => '1',
                'cookieTitle' => 'Cookies Consent',
                'strictlyCookieTitle' => 'Strictly Necessary Cookies',
                'cookieDescription' => '',
                'strictlyCookieDescription' => '',
            ],
        ],

        'storage' => [
            'label' => 'Storage Settings',
            'keys' => [
                'storageType' => ['nullable', 'string', Rule::in(['local', 's3', 'ftp', 'sftp'])],
                'allowedFileTypes' => ['nullable', 'string', 'max:255'],
                'maxUploadSize' => ['nullable', 'integer', 'between:1,262144'],
            ],
            'defaults' => [
                'storageType' => 'local',
                'allowedFileTypes' => 'png,jpg,jpeg,svg,pdf,doc,docx,xls,xlsx,csv',
                'maxUploadSize' => '2048',
            ],
        ],

        'email' => [
            'label' => 'Email Settings',
            'keys' => [
                'mailDriver' => ['nullable', 'string', Rule::in(['smtp', 'gmail', 'log', 'mailgun', 'ses', 'sendmail', 'array'])],
                'mailHost' => ['nullable', 'string', 'max:255'],
                'mailPort' => ['nullable', 'integer', 'between:1,65535'],
                'mailEncryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', ''])],
                'mailUsername' => ['nullable', 'string', 'max:255'],
                'mailPassword' => ['nullable', 'string'],
                'mailFromAddress' => ['nullable', 'email'],
                'mailFromName' => ['nullable', 'string', 'max:100'],
            ],
            'defaults' => [
                'mailDriver' => 'log',
                'mailHost' => '',
                'mailPort' => '587',
                'mailEncryption' => 'tls',
                'mailUsername' => null,
                'mailPassword' => null,
                'mailFromAddress' => null,
                'mailFromName' => 'Nexi ERP',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret keys
    |--------------------------------------------------------------------------
    | Values under these "group.key" names are encrypted at rest (defense in
    | depth) and decrypted transparently when read back.
    |--------------------------------------------------------------------------
    */

    'secrets' => [
        'email.mailPassword',
    ],

    /*
    |--------------------------------------------------------------------------
    | Options exposed to the settings UI
    |--------------------------------------------------------------------------
    */

    'options' => [
        'themes' => $themes,
        'themeColors' => $themeColors,
        'sidebarVariants' => ['one-page', 'spaced'],
        'layoutDirections' => ['ltr', 'rtl'],
        'dashboardWidgets' => $dashboardWidgets,
        'dashboardLayouts' => $dashboardLayouts,
        'dateFormats' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'd M, Y', 'd M Y'],
        'timeFormats' => ['H:i', 'h:i A', 'g:i A'],
        'calendarStartDays' => ['1', '2', '3', '4', '5', '6', '7'],
        'languages' => $languages,
        'emailProviders' => [
            'smtp' => 'SMTP',
            'gmail' => 'Gmail',
            'outlook' => 'Outlook',
            'yahoo' => 'Yahoo',
            'mailgun' => 'Mailgun',
            'ses' => 'Amazon SES',
            'sendgrid' => 'SendGrid',
            'postmark' => 'Postmark',
            'mailjet' => 'Mailjet',
            'sendinblue' => 'Sendinblue',
            'zoho' => 'Zoho',
            'mailchimp' => 'Mailchimp',
            'sendmail' => 'Sendmail',
            'log' => 'Log',
        ],
        'storageTypes' => ['local', 's3', 'ftp', 'sftp'],
        'currencyFormats' => ['0', '1', '2', '3'],
        'currencySymbolPositions' => ['left', 'right'],
    ],
];
