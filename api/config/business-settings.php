<?php

/*
|--------------------------------------------------------------------------
| Business Settings
|--------------------------------------------------------------------------
| Per-company business configuration surfaced under /api/v1/business-settings.
| The group structure mirrors the UltimatePOS Business Settings section.
|
| Each key definition:
|   label   - human readable label
|   type    - string|textarea|rich_text|boolean|integer|decimal|date|url|email|
|             secret|select|multi_select|array|object
|   default - value returned when nothing is persisted (booleans as '1'/'0')
|   rules   - extra Laravel validation rules appended by the request
|   options - inline option list, or a resolver name for optionsFor()
|--------------------------------------------------------------------------
*/

return [

    'groups' => [

        'business' => [
            'label' => 'Business',
            'description' => 'Core business identity, accounting and formatting configuration.',
            'keys' => [
                'business_name' => ['label' => 'Business Name', 'type' => 'string', 'default' => null, 'rules' => ['max:255']],
                'start_date' => ['label' => 'Start Date', 'type' => 'date', 'default' => null],
                'default_profit_percent' => ['label' => 'Default Profit Percent', 'type' => 'decimal', 'default' => '25.00'],
                'currency' => ['label' => 'Currency', 'type' => 'select', 'default' => 'USD', 'options' => 'currencies'],
                'currency_symbol_placement' => ['label' => 'Currency Symbol Placement', 'type' => 'select', 'default' => 'before_amount', 'options' => ['before_amount', 'after_amount']],
                'time_zone' => ['label' => 'Time Zone', 'type' => 'select', 'default' => 'UTC', 'options' => 'timezones'],
                'logo' => ['label' => 'Logo', 'type' => 'string', 'default' => null, 'rules' => ['max:255']],
                'financial_year_start_month' => ['label' => 'Financial Year Start Month', 'type' => 'select', 'default' => '1', 'options' => 'months'],
                'stock_accounting_method' => ['label' => 'Stock Accounting Method', 'type' => 'select', 'default' => 'FIFO', 'options' => ['FIFO', 'LIFO', 'AVCO']],
                'transaction_edit_days' => ['label' => 'Transaction Edit Days', 'type' => 'integer', 'default' => '30', 'rules' => ['min:0', 'max:3650']],
                'date_format' => ['label' => 'Date Format', 'type' => 'select', 'default' => 'mm/dd/yyyy', 'options' => 'date_formats'],
                'time_format' => ['label' => 'Time Format', 'type' => 'select', 'default' => '24 Hour', 'options' => 'time_formats'],
                'currency_precision' => ['label' => 'Currency Precision', 'type' => 'integer', 'default' => '2', 'rules' => ['min:0', 'max:8']],
                'quantity_precision' => ['label' => 'Quantity Precision', 'type' => 'integer', 'default' => '2', 'rules' => ['min:0', 'max:8']],
            ],
        ],

        'tax' => [
            'label' => 'Tax',
            'description' => 'Tax behaviour and invoice tax preferences.',
            'keys' => [
                'enable_inline_tax_in_purchase' => ['label' => 'Inline Tax In Purchase', 'type' => 'boolean', 'default' => '0'],
                'enable_inline_tax_in_sale' => ['label' => 'Inline Tax In Sale', 'type' => 'boolean', 'default' => '0'],
            ],
        ],

        'product' => [
            'label' => 'Product',
            'description' => 'Product catalogue behaviour, attributes and units.',
            'keys' => [
                'sku_prefix' => ['label' => 'SKU Prefix', 'type' => 'string', 'default' => null, 'rules' => ['max:20']],
                'enable_product_expiry' => ['label' => 'Enable Product Expiry', 'type' => 'boolean', 'default' => '0'],
                'add_item_expiry' => ['label' => 'Add Item Expiry', 'type' => 'boolean', 'default' => '0'],
                'enable_brands' => ['label' => 'Enable Brands', 'type' => 'boolean', 'default' => '1'],
                'enable_categories' => ['label' => 'Enable Categories', 'type' => 'boolean', 'default' => '1'],
                'enable_sub_categories' => ['label' => 'Enable Sub-Categories', 'type' => 'boolean', 'default' => '0'],
                'enable_price_tax_info' => ['label' => 'Enable Price & Tax Info', 'type' => 'boolean', 'default' => '1'],
                'default_unit' => ['label' => 'Default Unit', 'type' => 'string', 'default' => null, 'rules' => ['max:50']],
                'enable_sub_units' => ['label' => 'Enable Sub Units', 'type' => 'boolean', 'default' => '0'],
                'enable_racks' => ['label' => 'Enable Racks', 'type' => 'boolean', 'default' => '0'],
                'enable_row' => ['label' => 'Enable Row', 'type' => 'boolean', 'default' => '0'],
                'enable_position' => ['label' => 'Enable Position', 'type' => 'boolean', 'default' => '0'],
                'enable_warranty' => ['label' => 'Enable Warranty', 'type' => 'boolean', 'default' => '0'],
                'product_image_required' => ['label' => 'Is Product Image Required', 'type' => 'boolean', 'default' => '0'],
            ],
        ],

        'contact' => [
            'label' => 'Contact',
            'description' => 'Contact defaults such as credit limits.',
            'keys' => [
                'default_credit_limit' => ['label' => 'Default Credit Limit', 'type' => 'decimal', 'default' => null],
            ],
        ],

        'sale' => [
            'label' => 'Sale',
            'description' => 'Sale defaults, discounts, commissions and online payment integration.',
            'keys' => [
                'default_sale_discount' => ['label' => 'Default Sale Discount', 'type' => 'decimal', 'default' => '10.00'],
                'default_sale_tax' => ['label' => 'Default Sale Tax', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'sales_item_addition_method' => ['label' => 'Sales Item Addition Method', 'type' => 'select', 'default' => 'increase_quantity', 'options' => ['increase_quantity', 'add_new_item']],
                'amount_rounding_method' => ['label' => 'Amount Rounding Method', 'type' => 'select', 'default' => 'none', 'options' => ['none', 'nearest_whole', 'nearest_0_10']],
                'sales_price_minimum_selling_price' => ['label' => 'Sales Price Is Minimum Selling Price', 'type' => 'boolean', 'default' => '0'],
                'allow_overselling' => ['label' => 'Allow Overselling', 'type' => 'boolean', 'default' => '0'],
                'enable_sales_order' => ['label' => 'Enable Sales Order', 'type' => 'boolean', 'default' => '0'],
                'pay_term_required' => ['label' => 'Is Pay Term Required', 'type' => 'boolean', 'default' => '0'],
                'commission_agent' => ['label' => 'Sales Commission Agent', 'type' => 'select', 'default' => 'disable', 'options' => ['disable', 'logged_in_user', 'selected_user']],
                'commission_calculation_type' => ['label' => 'Commission Calculation Type', 'type' => 'select', 'default' => 'invoice_value', 'options' => ['invoice_value', 'profit']],
                'commission_agent_required' => ['label' => 'Is Commission Agent Required', 'type' => 'boolean', 'default' => '0'],
                'enable_payment_link' => ['label' => 'Enable Payment Link', 'type' => 'boolean', 'default' => '0'],
                'razorpay_key_id' => ['label' => 'Razorpay Key ID', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'razorpay_key_secret' => ['label' => 'Razorpay Key Secret', 'type' => 'secret', 'default' => ''],
                'stripe_public_key' => ['label' => 'Stripe Public Key', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'stripe_secret_key' => ['label' => 'Stripe Secret Key', 'type' => 'secret', 'default' => ''],
            ],
        ],

        'pos' => [
            'label' => 'POS',
            'description' => 'Point-of-sale screen behaviour, shortcuts and weighing scale.',
            'keys' => [
                'shortcut_express_checkout' => ['label' => 'Express Checkout', 'type' => 'string', 'default' => 'shift+e', 'rules' => ['max:50']],
                'shortcut_pay_and_checkout' => ['label' => 'Pay & Checkout', 'type' => 'string', 'default' => 'shift+p', 'rules' => ['max:50']],
                'shortcut_draft' => ['label' => 'Draft', 'type' => 'string', 'default' => 'shift+d', 'rules' => ['max:50']],
                'shortcut_cancel' => ['label' => 'Cancel', 'type' => 'string', 'default' => 'shift+c', 'rules' => ['max:50']],
                'shortcut_product_quantity' => ['label' => 'Go To Product Quantity', 'type' => 'string', 'default' => 'f2', 'rules' => ['max:50']],
                'shortcut_weighing_scale' => ['label' => 'Weighing Scale', 'type' => 'string', 'default' => '', 'rules' => ['max:50']],
                'shortcut_edit_discount' => ['label' => 'Edit Discount', 'type' => 'string', 'default' => 'shift+i', 'rules' => ['max:50']],
                'shortcut_edit_order_tax' => ['label' => 'Edit Order Tax', 'type' => 'string', 'default' => 'shift+t', 'rules' => ['max:50']],
                'shortcut_add_payment_row' => ['label' => 'Add Payment Row', 'type' => 'string', 'default' => 'shift+r', 'rules' => ['max:50']],
                'shortcut_finalize_payment' => ['label' => 'Finalize Payment', 'type' => 'string', 'default' => 'shift+f', 'rules' => ['max:50']],
                'shortcut_add_new_product' => ['label' => 'Add New Product', 'type' => 'string', 'default' => 'f4', 'rules' => ['max:50']],
                'disable_multiple_pay' => ['label' => 'Disable Multiple Pay', 'type' => 'boolean', 'default' => '0'],
                'disable_draft' => ['label' => 'Disable Draft', 'type' => 'boolean', 'default' => '0'],
                'disable_express_checkout' => ['label' => 'Disable Express Checkout', 'type' => 'boolean', 'default' => '0'],
                'donot_show_product_suggestion' => ['label' => "Don't Show Product Suggestion", 'type' => 'boolean', 'default' => '0'],
                'donot_show_recent_transactions' => ['label' => "Don't Show Recent Transactions", 'type' => 'boolean', 'default' => '0'],
                'disable_discount' => ['label' => 'Disable Discount', 'type' => 'boolean', 'default' => '0'],
                'disable_order_tax' => ['label' => 'Disable Order Tax', 'type' => 'boolean', 'default' => '0'],
                'subtotal_editable' => ['label' => 'Subtotal Editable', 'type' => 'boolean', 'default' => '0'],
                'disable_suspend_sale' => ['label' => 'Disable Suspend Sale', 'type' => 'boolean', 'default' => '0'],
                'enable_transaction_date_on_pos' => ['label' => 'Enable Transaction Date On POS Screen', 'type' => 'boolean', 'default' => '0'],
                'enable_service_staff_in_product_line' => ['label' => 'Enable Service Staff In Product Line', 'type' => 'boolean', 'default' => '0'],
                'service_staff_required' => ['label' => 'Is Service Staff Required', 'type' => 'boolean', 'default' => '0'],
                'disable_credit_sale_button' => ['label' => 'Disable Credit Sale Button', 'type' => 'boolean', 'default' => '0'],
                'enable_weighing_scale' => ['label' => 'Enable Weighing Scale', 'type' => 'boolean', 'default' => '0'],
                'show_invoice_scheme' => ['label' => 'Show Invoice Scheme', 'type' => 'boolean', 'default' => '0'],
                'show_invoice_layout_dropdown' => ['label' => 'Show Invoice Layout Dropdown', 'type' => 'boolean', 'default' => '0'],
                'print_invoice_on_suspend' => ['label' => 'Print Invoice On Suspend', 'type' => 'boolean', 'default' => '0'],
                'show_pricing_product_suggestion_tooltip' => ['label' => 'Show Pricing On Product Suggestion Tooltip', 'type' => 'boolean', 'default' => '0'],
                'weighing_scale_prefix' => ['label' => 'Weighing Scale Prefix', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'weighing_scale_product_sku_length' => ['label' => 'Weighing Scale Product SKU Length', 'type' => 'integer', 'default' => '5', 'rules' => ['min:1', 'max:20']],
                'weighing_scale_qty_integer_length' => ['label' => 'Weighing Scale Quantity Integer Part Length', 'type' => 'integer', 'default' => '4', 'rules' => ['min:1', 'max:10']],
                'weighing_scale_qty_fractional_length' => ['label' => 'Weighing Scale Quantity Fractional Part Length', 'type' => 'integer', 'default' => '3', 'rules' => ['min:0', 'max:10']],
            ],
        ],

        'display_screen' => [
            'label' => 'Display Screen',
            'description' => 'Customer facing display screen and carousel images.',
            'keys' => array_merge(
                [
                    'enable_customer_display_screen' => ['label' => 'Enable Customer Display Screen', 'type' => 'boolean', 'default' => '0'],
                    'display_screen_heading' => ['label' => 'Display Screen Heading', 'type' => 'rich_text', 'default' => ''],
                ],
                array_combine(
                    array_map(fn ($i) => 'carousel_image_'.$i, range(1, 10)),
                    array_map(fn ($i) => ['label' => "Carousel Image {$i}", 'type' => 'string', 'default' => '', 'rules' => ['max:255']], range(1, 10))
                )
            ),
        ],

        'purchases' => [
            'label' => 'Purchases',
            'description' => 'Purchase screen behaviour, lot numbers and requisitions.',
            'keys' => [
                'enable_edit_product_price_from_purchase' => ['label' => 'Enable Editing Product Price From Purchase Screen', 'type' => 'boolean', 'default' => '0'],
                'enable_purchase_status' => ['label' => 'Enable Purchase Status', 'type' => 'boolean', 'default' => '0'],
                'enable_lot_number' => ['label' => 'Enable Lot Number', 'type' => 'boolean', 'default' => '0'],
                'enable_purchase_order' => ['label' => 'Enable Purchase Order', 'type' => 'boolean', 'default' => '1'],
                'enable_purchase_requisition' => ['label' => 'Enable Purchase Requisition', 'type' => 'boolean', 'default' => '0'],
            ],
        ],

        'payment' => [
            'label' => 'Payment',
            'description' => 'Cash denominations and payment method behaviour.',
            'keys' => [
                'cash_denominations' => ['label' => 'Cash Denominations', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'enable_cash_denomination_on_pos' => ['label' => 'Enable Cash Denomination On POS Screen', 'type' => 'boolean', 'default' => '0'],
                'enable_cash_denomination_for_payment_methods' => ['label' => 'Enable Cash Denomination For Payment Methods', 'type' => 'multi_select', 'default' => ['cash', 'card'], 'options' => ['cash', 'card', 'cheque', 'bank_transfer', 'other', 'custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5', 'custom_6', 'custom_7']],
                'strict_check' => ['label' => 'Strict Check', 'type' => 'boolean', 'default' => '0'],
            ],
        ],

        'dashboard' => [
            'label' => 'Dashboard',
            'description' => 'Dashboard alerts and presentation.',
            'keys' => [
                'stock_expiry_alert_days' => ['label' => 'View Stock Expiry Alert For', 'type' => 'integer', 'default' => '30', 'rules' => ['min:1', 'max:365']],
            ],
        ],

        'system' => [
            'label' => 'System',
            'description' => 'System-level appearance and controls.',
            'keys' => [
                'theme_color' => ['label' => 'Theme Color', 'type' => 'select', 'default' => '', 'options' => 'theme_colors'],
                'default_datatable_page_entries' => ['label' => 'Default Datatable Page Entries', 'type' => 'integer', 'default' => '25', 'rules' => ['min:5', 'max:500']],
                'show_help_text' => ['label' => 'Show Help Text', 'type' => 'boolean', 'default' => '1'],
            ],
        ],

        'prefixes' => [
            'label' => 'Prefixes',
            'description' => 'Document number prefixes for generated references.',
            'keys' => [
                'purchase' => ['label' => 'Purchase', 'type' => 'string', 'default' => 'PO', 'rules' => ['max:20']],
                'purchase_return' => ['label' => 'Purchase Return', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'purchase_requisition' => ['label' => 'Purchase Requisition', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'purchase_order' => ['label' => 'Purchase Order', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'stock_transfer' => ['label' => 'Stock Transfer', 'type' => 'string', 'default' => 'ST', 'rules' => ['max:20']],
                'stock_adjustment' => ['label' => 'Stock Adjustment', 'type' => 'string', 'default' => 'SA', 'rules' => ['max:20']],
                'sell_return' => ['label' => 'Sell Return', 'type' => 'string', 'default' => 'CN', 'rules' => ['max:20']],
                'expenses' => ['label' => 'Expenses', 'type' => 'string', 'default' => 'EP', 'rules' => ['max:20']],
                'contacts' => ['label' => 'Contacts', 'type' => 'string', 'default' => 'CO', 'rules' => ['max:20']],
                'purchase_payment' => ['label' => 'Purchase Payment', 'type' => 'string', 'default' => 'PP', 'rules' => ['max:20']],
                'sell_payment' => ['label' => 'Sell Payment', 'type' => 'string', 'default' => 'SP', 'rules' => ['max:20']],
                'expense_payment' => ['label' => 'Expense Payment', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'business_location' => ['label' => 'Business Location', 'type' => 'string', 'default' => 'BL', 'rules' => ['max:20']],
                'username' => ['label' => 'Username', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'subscription_no' => ['label' => 'Subscription No.', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'draft' => ['label' => 'Draft', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
                'sales_order' => ['label' => 'Sales Order', 'type' => 'string', 'default' => '', 'rules' => ['max:20']],
            ],
        ],

        'email' => [
            'label' => 'Email Settings',
            'description' => 'Mail transport configuration used for notifications.',
            'keys' => [
                'mail_driver' => ['label' => 'Mail Driver', 'type' => 'select', 'default' => 'smtp', 'options' => 'mail_drivers'],
                'mail_host' => ['label' => 'Host', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'mail_port' => ['label' => 'Port', 'type' => 'integer', 'default' => '587', 'rules' => ['min:1', 'max:65535']],
                'mail_username' => ['label' => 'Username', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'mail_password' => ['label' => 'Password', 'type' => 'secret', 'default' => ''],
                'mail_encryption' => ['label' => 'Encryption', 'type' => 'select', 'default' => 'tls', 'options' => ['tls' => 'tls / ssl', '' => '', 'ssl' => 'ssl']],
                'mail_from_address' => ['label' => 'From Address', 'type' => 'email', 'default' => ''],
                'mail_from_name' => ['label' => 'From Name', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
            ],
        ],

        'sms' => [
            'label' => 'SMS Settings',
            'description' => 'SMS gateway transport used for notifications.',
            'keys' => [
                'sms_service' => ['label' => 'SMS Service', 'type' => 'select', 'default' => 'other', 'options' => ['other', 'twilio', 'nexmo', 'clickatell', 'africastalking', 'msg91', 'textlocal', 'plivo']],
                'sms_url' => ['label' => 'URL', 'type' => 'url', 'default' => ''],
                'send_to_parameter' => ['label' => 'SEND TO Parameter Name', 'type' => 'string', 'default' => 'to', 'rules' => ['max:50']],
                'message_parameter' => ['label' => 'MESSAGE Parameter Name', 'type' => 'string', 'default' => 'text', 'rules' => ['max:50']],
                'request_method' => ['label' => 'Request Method', 'type' => 'select', 'default' => 'POST', 'options' => ['POST' => 'POST', 'GET' => 'GET']],
                'data_parameter_type' => ['label' => 'Data Parameter Type', 'type' => 'select', 'default' => 'form_data', 'options' => ['form_data' => 'Form Data', 'query_string' => 'Query String']],
                'header_1_key' => ['label' => 'Header 1 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'header_1_value' => ['label' => 'Header 1 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'header_2_key' => ['label' => 'Header 2 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'header_2_value' => ['label' => 'Header 2 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'header_3_key' => ['label' => 'Header 3 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'header_3_value' => ['label' => 'Header 3 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_1_key' => ['label' => 'Parameter 1 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_1_value' => ['label' => 'Parameter 1 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_2_key' => ['label' => 'Parameter 2 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_2_value' => ['label' => 'Parameter 2 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_3_key' => ['label' => 'Parameter 3 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_3_value' => ['label' => 'Parameter 3 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_4_key' => ['label' => 'Parameter 4 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_4_value' => ['label' => 'Parameter 4 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_5_key' => ['label' => 'Parameter 5 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_5_value' => ['label' => 'Parameter 5 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_6_key' => ['label' => 'Parameter 6 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_6_value' => ['label' => 'Parameter 6 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_7_key' => ['label' => 'Parameter 7 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_7_value' => ['label' => 'Parameter 7 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_8_key' => ['label' => 'Parameter 8 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_8_value' => ['label' => 'Parameter 8 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_9_key' => ['label' => 'Parameter 9 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_9_value' => ['label' => 'Parameter 9 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
                'parameter_10_key' => ['label' => 'Parameter 10 Key', 'type' => 'string', 'default' => '', 'rules' => ['max:100']],
                'parameter_10_value' => ['label' => 'Parameter 10 Value', 'type' => 'string', 'default' => '', 'rules' => ['max:255']],
            ],
        ],

        'reward_point' => [
            'label' => 'Reward Point Settings',
            'description' => 'Loyalty reward point earning and redemption rules.',
            'keys' => [
                'enable_reward_point' => ['label' => 'Enable Reward Point', 'type' => 'boolean', 'default' => '0'],
                'reward_point_display_name' => ['label' => 'Reward Point Display Name', 'type' => 'string', 'default' => 'Reward Point', 'rules' => ['max:100']],
                'earn_amount_spend_for_unit_point' => ['label' => 'Amount Spend For Unit Point', 'type' => 'decimal', 'default' => '1.00'],
                'earn_minimum_order_total' => ['label' => 'Minimum Order Total To Earn Reward', 'type' => 'decimal', 'default' => '1.00'],
                'earn_maximum_points_per_order' => ['label' => 'Maximum Points Per Order', 'type' => 'decimal', 'default' => ''],
                'redeem_amount_per_unit_point' => ['label' => 'Redeem Amount Per Unit Point', 'type' => 'decimal', 'default' => '1.00'],
                'redeem_minimum_order_total' => ['label' => 'Minimum Order Total To Redeem Points', 'type' => 'decimal', 'default' => '1.00'],
                'redeem_minimum_points' => ['label' => 'Minimum Redeem Point', 'type' => 'decimal', 'default' => ''],
                'redeem_maximum_points_per_order' => ['label' => 'Maximum Redeem Point Per Order', 'type' => 'decimal', 'default' => ''],
                'expiry_period' => ['label' => 'Reward Point Expiry Period', 'type' => 'integer', 'default' => '', 'rules' => ['min:1', 'max:3650']],
                'expiry_period_unit' => ['label' => 'Reward Point Expiry Period Unit', 'type' => 'select', 'default' => 'year', 'options' => ['year', 'month', 'day']],
            ],
        ],

        'modules' => [
            'label' => 'Modules',
            'description' => 'Enable or disable business modules.',
            'keys' => [
                'enable_purchases' => ['label' => 'Purchases', 'type' => 'boolean', 'default' => '1'],
                'enable_add_sale' => ['label' => 'Add Sale', 'type' => 'boolean', 'default' => '1'],
                'enable_pos' => ['label' => 'POS', 'type' => 'boolean', 'default' => '1'],
                'enable_stock_transfers' => ['label' => 'Stock Transfers', 'type' => 'boolean', 'default' => '1'],
                'enable_stock_adjustment' => ['label' => 'Stock Adjustment', 'type' => 'boolean', 'default' => '1'],
                'enable_expenses' => ['label' => 'Expenses', 'type' => 'boolean', 'default' => '1'],
                'enable_account' => ['label' => 'Account', 'type' => 'boolean', 'default' => '1'],
                'enable_tables' => ['label' => 'Tables', 'type' => 'boolean', 'default' => '0'],
                'enable_modifiers' => ['label' => 'Modifiers', 'type' => 'boolean', 'default' => '0'],
                'enable_service_staff' => ['label' => 'Service Staff', 'type' => 'boolean', 'default' => '0'],
                'enable_bookings' => ['label' => 'Enable Bookings', 'type' => 'boolean', 'default' => '0'],
                'enable_kitchen' => ['label' => 'Kitchen', 'type' => 'boolean', 'default' => '0'],
                'enable_subscription' => ['label' => 'Enable Subscription', 'type' => 'boolean', 'default' => '0'],
                'enable_types_of_service' => ['label' => 'Types Of Service', 'type' => 'boolean', 'default' => '0'],
            ],
        ],

        'custom_labels' => [
            'label' => 'Custom Labels',
            'description' => 'Custom labels for payments, contacts, products, locations, users, purchases and sales.',
            'keys' => array_merge(
                array_combine(
                    array_map(fn ($i) => 'custom_payment_'.$i, range(1, 7)),
                    array_map(fn ($i) => ['label' => "Custom Payment {$i}", 'type' => 'string', 'default' => 'Custom Payment '.$i, 'rules' => ['max:50']], range(1, 7))
                ),
                array_combine(
                    array_map(fn ($i) => 'contact_custom_field_'.$i, range(1, 10)),
                    array_map(fn ($i) => ['label' => "Contact Custom Field {$i}", 'type' => 'string', 'default' => 'Custom Field '.$i, 'rules' => ['max:50']], range(1, 10))
                ),
                array_combine(
                    array_map(fn ($i) => 'product_custom_field_'.$i, range(1, 20)),
                    array_map(fn ($i) => ['label' => "Product Custom Field {$i}", 'type' => 'object', 'default' => ['label' => 'Custom Field '.$i, 'field_type' => 'text'], 'rules' => ['array']], range(1, 20))
                ),
                array_combine(
                    array_map(fn ($i) => 'location_custom_field_'.$i, range(1, 4)),
                    array_map(fn ($i) => ['label' => "Location Custom Field {$i}", 'type' => 'string', 'default' => 'Custom Field '.$i, 'rules' => ['max:50']], range(1, 4))
                ),
                array_combine(
                    array_map(fn ($i) => 'user_custom_field_'.$i, range(1, 4)),
                    array_map(fn ($i) => ['label' => "User Custom Field {$i}", 'type' => 'string', 'default' => 'Custom Field '.$i, 'rules' => ['max:50']], range(1, 4))
                ),
                array_combine(
                    array_map(fn ($i) => 'purchase_custom_field_'.$i, range(1, 4)),
                    array_map(fn ($i) => ['label' => "Purchase Custom Field {$i}", 'type' => 'object', 'default' => ['label' => 'Custom Field '.$i, 'required' => false], 'rules' => ['array']], range(1, 4))
                ),
                array_combine(
                    array_map(fn ($i) => 'purchase_shipping_custom_field_'.$i, range(1, 5)),
                    array_map(fn ($i) => ['label' => "Purchase Shipping Custom Field {$i}", 'type' => 'object', 'default' => ['label' => 'Custom Field '.$i, 'required' => false], 'rules' => ['array']], range(1, 5))
                ),
                array_combine(
                    array_map(fn ($i) => 'sell_custom_field_'.$i, range(1, 4)),
                    array_map(fn ($i) => ['label' => "Sell Custom Field {$i}", 'type' => 'object', 'default' => ['label' => 'Custom Field '.$i, 'required' => false], 'rules' => ['array']], range(1, 4))
                ),
                array_combine(
                    array_map(fn ($i) => 'sale_shipping_custom_field_'.$i, range(1, 5)),
                    array_map(fn ($i) => ['label' => "Sale Shipping Custom Field {$i}", 'type' => 'object', 'default' => ['label' => 'Custom Field '.$i, 'required' => false, 'default_for_contact' => false], 'rules' => ['array']], range(1, 5))
                ),
                array_combine(
                    array_map(fn ($i) => 'types_of_service_custom_field_'.$i, range(1, 6)),
                    array_map(fn ($i) => ['label' => "Types Of Service Custom Field {$i}", 'type' => 'string', 'default' => 'Custom Field '.$i, 'rules' => ['max:50']], range(1, 6))
                )
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Shared option lists resolved by optionsFor()
    |--------------------------------------------------------------------------
    */

    'option_list' => [
        'months' => ['1' => 'January', '2' => 'February', '3' => 'March', '4' => 'April', '5' => 'May', '6' => 'June', '7' => 'July', '8' => 'August', '9' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'],
        'date_formats' => ['mm/dd/yyyy', 'dd/mm/yyyy', 'dd-mm-yyyy', 'dd.mm.yyyy', 'yyyy-mm-dd', 'd/m/Y'],
        'time_formats' => ['24 Hour', '12 Hour'],
        'theme_colors' => ['blue', 'blue_light', 'yellow', 'red', 'orange', 'green'],
        'mail_drivers' => ['smtp' => 'SMTP', 'gmail' => 'Gmail', 'outlook' => 'Outlook', 'yahoo' => 'Yahoo', 'mailgun' => 'Mailgun', 'ses' => 'Amazon SES', 'sendgrid' => 'SendGrid', 'postmark' => 'Postmark', 'sendmail' => 'Sendmail', 'log' => 'Log'],
    ],
];
