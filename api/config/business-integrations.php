<?php

/*
|--------------------------------------------------------------------------
| Business Integration Catalog
|--------------------------------------------------------------------------
| Declarative provider catalog consumed by App\Support\IntegrationGroupDefinitions
| to generate typed settings groups under /api/v1/business-settings.
|
| Each provider:
|   category    - key into `categories`
|   label       - group label
|   description - optional; defaults to a generated sentence
|   fields      - credential/configuration fields. Each field is either:
|                   'name' => 'type'                 (label derived from name)
|                   'name' => ['label' => ..., 'type' => ..., 'default' => ..., 'options' => [...]]
|
| Supported field types: boolean, secret, string, url, email, integer, select.
| Every generated group also receives an `enabled` boolean toggle.
|--------------------------------------------------------------------------
*/

return [
    'categories' => [
        'payment_gateway' => 'Payment Gateways',
        'sms' => 'SMS Providers',
        'chat' => 'Chat & Messaging',
        'ai' => 'AI Settings',
        'social' => 'Social Media',
        'automation' => 'Automation',
        'banking' => 'Banking',
        'storage' => 'Storage & DMS',
        'meeting' => 'Meetings & Video',
        'crm' => 'CRM & Helpdesk',
        'ecommerce' => 'E-commerce & Accounting',
        'marketing' => 'Email & Marketing',
        'einvoice' => 'E-Invoicing',
    ],

    'providers' => [
        // -------------------------------------------------------------
        // Payment gateways
        // -------------------------------------------------------------
        'stripe' => ['category' => 'payment_gateway', 'label' => 'Stripe Settings', 'fields' => [
            'publishable_key' => 'secret',
            'secret_key' => 'secret',
            'webhook_secret' => 'secret',
        ]],
        'paypal' => ['category' => 'payment_gateway', 'label' => 'PayPal Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'razorpay' => ['category' => 'payment_gateway', 'label' => 'Razorpay Settings', 'fields' => [
            'key_id' => 'secret',
            'key_secret' => 'secret',
        ]],
        'flutterwave' => ['category' => 'payment_gateway', 'label' => 'Flutterwave Settings', 'fields' => [
            'public_key' => 'secret',
            'secret_key' => 'secret',
            'encryption_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'paystack' => ['category' => 'payment_gateway', 'label' => 'Paystack Settings', 'fields' => [
            'public_key' => 'secret',
            'secret_key' => 'secret',
        ]],
        'mollie' => ['category' => 'payment_gateway', 'label' => 'Mollie Settings', 'fields' => [
            'api_key' => 'secret',
        ]],
        'payfast' => ['category' => 'payment_gateway', 'label' => 'PayFast Settings', 'fields' => [
            'merchant_id' => 'secret',
            'merchant_key' => 'secret',
            'passphrase' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'yookassa' => ['category' => 'payment_gateway', 'label' => 'YooKassa Settings', 'fields' => [
            'shop_id' => 'secret',
            'secret_key' => 'secret',
        ]],
        'paytab' => ['category' => 'payment_gateway', 'label' => 'PayTabs Settings', 'fields' => [
            'profile_id' => 'secret',
            'server_key' => 'secret',
            'region' => ['label' => 'Region', 'type' => 'select', 'default' => 'GLOBAL', 'options' => ['GLOBAL', 'ARE', 'EGY', 'SAU']],
        ]],
        'toyyibpay' => ['category' => 'payment_gateway', 'label' => 'ToyyibPay Settings', 'fields' => [
            'user_secret' => 'secret',
            'category_code' => 'string',
        ]],
        'skrill' => ['category' => 'payment_gateway', 'label' => 'Skrill Settings', 'fields' => [
            'merchant_email' => 'email',
            'secret_word' => 'secret',
        ]],
        'iyzico' => ['category' => 'payment_gateway', 'label' => 'Iyzico Settings', 'fields' => [
            'api_key' => 'secret',
            'secret_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'paytr' => ['category' => 'payment_gateway', 'label' => 'PayTR Settings', 'fields' => [
            'merchant_id' => 'secret',
            'merchant_key' => 'secret',
            'merchant_salt' => 'secret',
        ]],
        'aamarpay' => ['category' => 'payment_gateway', 'label' => 'Aamarpay Settings', 'fields' => [
            'store_id' => 'secret',
            'signature_key' => 'secret',
        ]],
        'checkout_2' => ['category' => 'payment_gateway', 'label' => '2Checkout Settings', 'fields' => [
            'merchant_code' => 'secret',
            'secret_key' => 'secret',
        ]],
        'mercado_pago' => ['category' => 'payment_gateway', 'label' => 'Mercado Pago Settings', 'fields' => [
            'public_key' => 'secret',
            'access_token' => 'secret',
        ]],
        'paytm' => ['category' => 'payment_gateway', 'label' => 'Paytm Settings', 'fields' => [
            'merchant_id' => 'secret',
            'merchant_key' => 'secret',
            'merchant_website' => 'string',
            'channel' => 'string',
            'industry_type' => 'string',
        ]],
        'midtrans' => ['category' => 'payment_gateway', 'label' => 'Midtrans Settings', 'fields' => [
            'server_key' => 'secret',
            'client_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'xendit' => ['category' => 'payment_gateway', 'label' => 'Xendit Settings', 'fields' => [
            'secret_key' => 'secret',
        ]],
        'tap' => ['category' => 'payment_gateway', 'label' => 'Tap Settings', 'fields' => [
            'secret_key' => 'secret',
            'currency' => 'string',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'khalti' => ['category' => 'payment_gateway', 'label' => 'Khalti Settings', 'fields' => [
            'public_key' => 'secret',
            'secret_key' => 'secret',
        ]],
        'phonepe' => ['category' => 'payment_gateway', 'label' => 'PhonePe Settings', 'fields' => [
            'merchant_id' => 'secret',
            'salt_key' => 'secret',
            'salt_index' => 'integer',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'authorize_net' => ['category' => 'payment_gateway', 'label' => 'Authorize.Net Settings', 'fields' => [
            'login_id' => 'secret',
            'transaction_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'payhere' => ['category' => 'payment_gateway', 'label' => 'PayHere Settings', 'fields' => [
            'merchant_id' => 'secret',
            'merchant_secret' => 'secret',
        ]],
        'paiementpro' => ['category' => 'payment_gateway', 'label' => 'PaiementPro Settings', 'fields' => [
            'public_key' => 'secret',
            'private_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'fedapay' => ['category' => 'payment_gateway', 'label' => 'FedaPay Settings', 'fields' => [
            'secret_key' => 'secret',
            'public_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'cinetpay' => ['category' => 'payment_gateway', 'label' => 'CinetPay Settings', 'fields' => [
            'site_id' => 'secret',
            'api_key' => 'secret',
        ]],
        'senangpay' => ['category' => 'payment_gateway', 'label' => 'SenangPay Settings', 'fields' => [
            'merchant_id' => 'secret',
            'merchant_hash' => 'secret',
        ]],
        'cybersource' => ['category' => 'payment_gateway', 'label' => 'CyberSource Settings', 'fields' => [
            'merchant_id' => 'secret',
            'api_key_id' => 'secret',
            'secret_key' => 'secret',
        ]],
        'ozow' => ['category' => 'payment_gateway', 'label' => 'Ozow Settings', 'fields' => [
            'site_code' => 'secret',
            'private_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'easebuzz' => ['category' => 'payment_gateway', 'label' => 'Easebuzz Settings', 'fields' => [
            'merchant_key' => 'secret',
            'merchant_salt' => 'secret',
        ]],
        'square' => ['category' => 'payment_gateway', 'label' => 'Square Settings', 'fields' => [
            'access_token' => 'secret',
            'location_id' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'braintree' => ['category' => 'payment_gateway', 'label' => 'Braintree Settings', 'fields' => [
            'merchant_id' => 'secret',
            'public_key' => 'secret',
            'private_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'instamojo' => ['category' => 'payment_gateway', 'label' => 'Instamojo Settings', 'fields' => [
            'api_key' => 'secret',
            'auth_token' => 'secret',
        ]],
        'esewa' => ['category' => 'payment_gateway', 'label' => 'eSewa Settings', 'fields' => [
            'merchant_code' => 'secret',
            'merchant_secret' => 'string',
        ]],
        'paynow' => ['category' => 'payment_gateway', 'label' => 'Paynow Settings', 'fields' => [
            'integration_key' => 'secret',
            'integration_id' => 'secret',
        ]],
        'myfatoorah' => ['category' => 'payment_gateway', 'label' => 'MyFatoorah Settings', 'fields' => [
            'api_token' => 'secret',
            'country_code' => 'string',
        ]],
        'fatora' => ['category' => 'payment_gateway', 'label' => 'Fatora Settings', 'fields' => [
            'secret_key' => 'secret',
        ]],
        'yoco' => ['category' => 'payment_gateway', 'label' => 'Yoco Settings', 'fields' => [
            'secret_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'moyasar' => ['category' => 'payment_gateway', 'label' => 'Moyasar Settings', 'fields' => [
            'secret_key' => 'secret',
            'public_key' => 'secret',
        ]],
        'nmi' => ['category' => 'payment_gateway', 'label' => 'NMI Settings', 'fields' => [
            'api_key' => 'secret',
        ]],
        'powertranz' => ['category' => 'payment_gateway', 'label' => 'PowerTranz Settings', 'fields' => [
            'token' => 'secret',
            'merchant_id' => 'secret',
        ]],
        'dpopay' => ['category' => 'payment_gateway', 'label' => 'DPO Pay Settings', 'fields' => [
            'company_token' => 'secret',
            'payment_service_type' => 'string',
        ]],
        'monnify' => ['category' => 'payment_gateway', 'label' => 'Monnify Settings', 'fields' => [
            'secret_key' => 'secret',
            'api_key' => 'secret',
            'contract_code' => 'string',
        ]],
        'uddoktapay' => ['category' => 'payment_gateway', 'label' => 'UddoktaPay Settings', 'fields' => [
            'api_key' => 'secret',
            'api_url' => 'url',
        ]],
        'moneris' => ['category' => 'payment_gateway', 'label' => 'Moneris Settings', 'fields' => [
            'api_key' => 'secret',
            'store_id' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'paypay' => ['category' => 'payment_gateway', 'label' => 'PayPay Settings', 'fields' => [
            'api_key' => 'secret',
            'api_secret' => 'secret',
            'merchant_id' => 'secret',
        ]],
        'pesapal' => ['category' => 'payment_gateway', 'label' => 'Pesapal Settings', 'fields' => [
            'consumer_key' => 'secret',
            'consumer_secret' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'coinbase' => ['category' => 'payment_gateway', 'label' => 'Coinbase Commerce Settings', 'fields' => [
            'api_key' => 'secret',
            'webhook_shared_secret' => 'secret',
        ]],
        'btcpayserver' => ['category' => 'payment_gateway', 'label' => 'BTCPayServer Settings', 'fields' => [
            'merchant_id' => 'secret',
            'api_key' => 'secret',
            'store_id' => 'secret',
            'server_url' => 'url',
        ]],
        'payfort' => ['category' => 'payment_gateway', 'label' => 'PayFort Settings', 'fields' => [
            'merchant_identifier' => 'secret',
            'access_code' => 'secret',
            'sha_request_phrase' => 'secret',
            'sha_response_phrase' => 'secret',
        ]],
        'bluesnap' => ['category' => 'payment_gateway', 'label' => 'BlueSnap Settings', 'fields' => [
            'api_username' => 'secret',
            'api_password' => 'secret',
        ]],
        'linepay' => ['category' => 'payment_gateway', 'label' => 'LINE Pay Settings', 'fields' => [
            'channel_id' => 'secret',
            'channel_secret' => 'secret',
        ]],
        'bitpay' => ['category' => 'payment_gateway', 'label' => 'BitPay Settings', 'fields' => [
            'token' => 'secret',
        ]],
        'peach' => ['category' => 'payment_gateway', 'label' => 'Peach Payments Settings', 'fields' => [
            'entity_id' => 'secret',
            'access_token' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'sslcommerz' => ['category' => 'payment_gateway', 'label' => 'SSLCommerz Settings', 'fields' => [
            'store_id' => 'secret',
            'store_password' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'adyen' => ['category' => 'payment_gateway', 'label' => 'Adyen Settings', 'fields' => [
            'merchant_account' => 'secret',
            'api_key' => 'secret',
            'client_key' => 'secret',
            'environment' => ['label' => 'Environment', 'type' => 'select', 'default' => 'test', 'options' => ['test', 'live']],
        ]],
        'checkout_com' => ['category' => 'payment_gateway', 'label' => 'Checkout.com Settings', 'fields' => [
            'secret_key' => 'secret',
            'public_key' => 'secret',
        ]],

        // -------------------------------------------------------------
        // SMS providers
        // -------------------------------------------------------------
        'twilio' => ['category' => 'sms', 'label' => 'Twilio Settings', 'fields' => [
            'account_sid' => 'secret',
            'auth_token' => 'secret',
            'from' => 'string',
            'verify_service_sid' => 'secret',
        ]],
        'clicksend' => ['category' => 'sms', 'label' => 'ClickSend Settings', 'fields' => [
            'username' => 'string',
            'api_key' => 'secret',
        ]],
        'whatsender' => ['category' => 'sms', 'label' => 'Whatsender Settings', 'fields' => [
            'api_key' => 'secret',
            'instance_id' => 'secret',
        ]],
        'africa_talking' => ['category' => 'sms', 'label' => "Africa's Talking Settings", 'fields' => [
            'username' => 'string',
            'api_key' => 'secret',
            'sender_id' => 'string',
        ]],
        'sinch' => ['category' => 'sms', 'label' => 'Sinch Settings', 'fields' => [
            'service_plan_id' => 'secret',
            'api_token' => 'secret',
            'from' => 'string',
        ]],
        'msg91' => ['category' => 'sms', 'label' => 'MSG91 Settings', 'fields' => [
            'auth_key' => 'secret',
            'sender_id' => 'string',
            'route' => 'string',
            'country' => 'integer',
        ]],
        'telesign' => ['category' => 'sms', 'label' => 'Telesign Settings', 'fields' => [
            'customer_id' => 'secret',
            'api_key' => 'secret',
        ]],
        'plivo' => ['category' => 'sms', 'label' => 'Plivo Settings', 'fields' => [
            'auth_id' => 'secret',
            'auth_token' => 'secret',
            'from' => 'string',
        ]],
        'zita' => ['category' => 'sms', 'label' => 'Zita Settings', 'fields' => [
            'api_key' => 'secret',
            'account_id' => 'secret',
        ]],
        'vonage' => ['category' => 'sms', 'label' => 'Vonage Settings', 'fields' => [
            'api_key' => 'secret',
            'api_secret' => 'secret',
            'from' => 'string',
        ]],
        'fast2sms' => ['category' => 'sms', 'label' => 'Fast2SMS Settings', 'fields' => [
            'api_key' => 'secret',
            'sender_id' => 'string',
        ]],
        'clickatell' => ['category' => 'sms', 'label' => 'Clickatell Settings', 'fields' => [
            'api_key' => 'secret',
            'from' => 'string',
        ]],
        'textlocal' => ['category' => 'sms', 'label' => 'Textlocal Settings', 'fields' => [
            'api_key' => 'secret',
            'sender' => 'string',
        ]],

        // -------------------------------------------------------------
        // Chat & messaging
        // -------------------------------------------------------------
        'slack' => ['category' => 'chat', 'label' => 'Slack Settings', 'fields' => [
            'bot_token' => 'secret',
            'channel' => 'string',
        ]],
        'telegram' => ['category' => 'chat', 'label' => 'Telegram Settings', 'fields' => [
            'bot_token' => 'secret',
            'chat_id' => 'string',
        ]],
        'discord' => ['category' => 'chat', 'label' => 'Discord Settings', 'fields' => [
            'bot_token' => 'secret',
            'webhook_url' => 'url',
        ]],
        'rocket_chat' => ['category' => 'chat', 'label' => 'RocketChat Settings', 'fields' => [
            'server_url' => 'url',
            'user_id' => 'secret',
            'auth_token' => 'secret',
        ]],
        'whatsapp_messenger' => ['category' => 'chat', 'label' => 'WhatsApp Messenger Settings', 'fields' => [
            'access_token' => 'secret',
            'phone_number_id' => 'secret',
            'business_account_id' => 'secret',
        ]],
        'whatsapp_chat' => ['category' => 'chat', 'label' => 'WhatsApp Chat Settings', 'fields' => [
            'api_key' => 'secret',
            'instance_id' => 'secret',
        ]],
        'instagram_chat' => ['category' => 'chat', 'label' => 'Instagram Chat Settings', 'fields' => [
            'access_token' => 'secret',
            'page_id' => 'string',
        ]],
        'facebook_chat' => ['category' => 'chat', 'label' => 'Facebook Chat Settings', 'fields' => [
            'access_token' => 'secret',
            'page_id' => 'string',
        ]],
        'zulip_chat' => ['category' => 'chat', 'label' => 'Zulip Chat Settings', 'fields' => [
            'server_url' => 'url',
            'api_key' => 'secret',
            'email' => 'email',
        ]],
        'tawk_to' => ['category' => 'chat', 'label' => 'Tawk.to Settings', 'fields' => [
            'property_id' => 'string',
            'widget_id' => 'string',
        ]],
        'wizzchat' => ['category' => 'chat', 'label' => 'WizzChat Settings', 'fields' => [
            'api_key' => 'secret',
            'organization_id' => 'secret',
        ]],

        // -------------------------------------------------------------
        // AI settings
        // -------------------------------------------------------------
        'ai_assistant' => ['category' => 'ai', 'label' => 'AI Assistant Settings', 'fields' => [
            'assistant_provider' => ['label' => 'Assistant Provider', 'type' => 'select', 'default' => 'gemini', 'options' => ['gemini', 'openai', 'mistral']],
            'api_key' => 'secret',
            'model' => 'string',
            'system_prompt' => 'string',
        ]],
        'ai_image' => ['category' => 'ai', 'label' => 'AI Image Settings', 'fields' => [
            'image_provider' => ['label' => 'Image Provider', 'type' => 'select', 'default' => 'gemini', 'options' => ['gemini', 'openai', 'mistral']],
            'api_key' => 'secret',
            'model' => 'string',
        ]],
        'ai_document' => ['category' => 'ai', 'label' => 'AI Document Settings', 'fields' => [
            'document_provider' => ['label' => 'Document Provider', 'type' => 'select', 'default' => 'gemini', 'options' => ['gemini', 'openai', 'mistral']],
            'api_key' => 'secret',
            'model' => 'string',
        ]],

        // -------------------------------------------------------------
        // Social media
        // -------------------------------------------------------------
        'youtube' => ['category' => 'social', 'label' => 'YouTube Settings', 'fields' => [
            'api_key' => 'secret',
            'channel_id' => 'string',
        ]],
        'facebook_post' => ['category' => 'social', 'label' => 'Facebook Post Settings', 'fields' => [
            'access_token' => 'secret',
            'page_id' => 'string',
        ]],
        'instagram_post' => ['category' => 'social', 'label' => 'Instagram Post Settings', 'fields' => [
            'access_token' => 'secret',
            'account_id' => 'string',
        ]],
        'google_wallet' => ['category' => 'social', 'label' => 'Google Wallet Settings', 'fields' => [
            'issuer_id' => 'secret',
            'service_account_json' => 'secret',
        ]],

        // -------------------------------------------------------------
        // Automation
        // -------------------------------------------------------------
        'zapier' => ['category' => 'automation', 'label' => 'Zapier Settings', 'fields' => [
            'api_key' => 'secret',
            'hook_url' => 'url',
        ]],
        'make' => ['category' => 'automation', 'label' => 'Make Settings', 'fields' => [
            'api_key' => 'secret',
            'hook_url' => 'url',
        ]],
        'n8n' => ['category' => 'automation', 'label' => 'n8n Settings', 'fields' => [
            'api_key' => 'secret',
            'instance_url' => 'url',
        ]],
        'pabbly' => ['category' => 'automation', 'label' => 'Pabbly Settings', 'fields' => [
            'hook_url' => 'url',
        ]],
        'webhook' => ['category' => 'automation', 'label' => 'Webhook Settings', 'fields' => [
            'webhook_secret' => 'secret',
            'notification_url' => 'url',
        ]],

        // -------------------------------------------------------------
        // Banking
        // -------------------------------------------------------------
        'plaid' => ['category' => 'banking', 'label' => 'Plaid Settings', 'fields' => [
            'client_id' => 'secret',
            'secret_key' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
            'country_code' => 'string',
            'language' => 'string',
        ]],

        // -------------------------------------------------------------
        // Storage & DMS
        // -------------------------------------------------------------
        'google_drive' => ['category' => 'storage', 'label' => 'Google Drive Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'google_sheets' => ['category' => 'storage', 'label' => 'Google Sheets Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'google_docs' => ['category' => 'storage', 'label' => 'Google Docs Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'google_slides' => ['category' => 'storage', 'label' => 'Google Slides Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'google_forms' => ['category' => 'storage', 'label' => 'Google Forms Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'google_calendar' => ['category' => 'storage', 'label' => 'Google Calendar Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'outlook_calendar' => ['category' => 'storage', 'label' => 'Outlook Calendar Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'one_drive' => ['category' => 'storage', 'label' => 'OneDrive Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'one_note' => ['category' => 'storage', 'label' => 'OneNote Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'box' => ['category' => 'storage', 'label' => 'Box Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'dropbox' => ['category' => 'storage', 'label' => 'Dropbox Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],

        // -------------------------------------------------------------
        // Meetings & video
        // -------------------------------------------------------------
        'zoom' => ['category' => 'meeting', 'label' => 'Zoom Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'jitsi' => ['category' => 'meeting', 'label' => 'Jitsi Settings', 'fields' => [
            'server_url' => 'url',
            'app_id' => 'string',
            'app_secret' => 'secret',
        ]],
        'zoho_meeting' => ['category' => 'meeting', 'label' => 'Zoho Meeting Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'redirect_uri' => 'url',
        ]],
        'livestorm' => ['category' => 'meeting', 'label' => 'Livestorm Settings', 'fields' => [
            'api_key' => 'secret',
        ]],
        'whereby' => ['category' => 'meeting', 'label' => 'Whereby Settings', 'fields' => [
            'api_key' => 'secret',
            'subdomain' => 'string',
        ]],

        // -------------------------------------------------------------
        // CRM & helpdesk
        // -------------------------------------------------------------
        'salesforce' => ['category' => 'crm', 'label' => 'SalesForce Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'username' => 'string',
            'password' => 'secret',
            'security_token' => 'secret',
        ]],
        'pipedrive' => ['category' => 'crm', 'label' => 'Pipedrive Settings', 'fields' => [
            'api_token' => 'secret',
            'domain' => 'string',
        ]],
        'hubspot' => ['category' => 'crm', 'label' => 'HubSpot Settings', 'fields' => [
            'api_key' => 'secret',
        ]],
        'hubspot_support' => ['category' => 'crm', 'label' => 'HubSpot Support Settings', 'fields' => [
            'api_key' => 'secret',
            'portal_id' => 'string',
        ]],
        'zendesk' => ['category' => 'crm', 'label' => 'Zendesk Settings', 'fields' => [
            'subdomain' => 'string',
            'api_token' => 'secret',
            'email' => 'email',
        ]],
        'trello' => ['category' => 'crm', 'label' => 'Trello Settings', 'fields' => [
            'api_key' => 'secret',
            'api_token' => 'secret',
        ]],
        'jira' => ['category' => 'crm', 'label' => 'Jira Settings', 'fields' => [
            'base_url' => 'url',
            'api_email' => 'email',
            'api_token' => 'secret',
        ]],
        'asana' => ['category' => 'crm', 'label' => 'Asana Settings', 'fields' => [
            'access_token' => 'secret',
        ]],
        'whmcs' => ['category' => 'crm', 'label' => 'WHMCS Settings', 'fields' => [
            'api_identifier' => 'secret',
            'api_secret' => 'secret',
            'base_url' => 'url',
        ]],
        'indiamart' => ['category' => 'crm', 'label' => 'Indiamart Settings', 'fields' => [
            'api_key' => 'secret',
            'crm_key' => 'secret',
        ]],

        // -------------------------------------------------------------
        // E-commerce & accounting
        // -------------------------------------------------------------
        'shopify' => ['category' => 'ecommerce', 'label' => 'Shopify Settings', 'fields' => [
            'shop_url' => 'url',
            'api_key' => 'secret',
            'api_secret' => 'secret',
            'access_token' => 'secret',
        ]],
        'woocommerce' => ['category' => 'ecommerce', 'label' => 'WooCommerce Settings', 'fields' => [
            'store_url' => 'url',
            'consumer_key' => 'secret',
            'consumer_secret' => 'secret',
        ]],
        'quickbooks' => ['category' => 'ecommerce', 'label' => 'QuickBooks Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'realm_id' => 'secret',
            'refresh_token' => 'secret',
            'mode' => ['label' => 'Mode', 'type' => 'select', 'default' => 'sandbox', 'options' => ['sandbox', 'live']],
        ]],
        'xero' => ['category' => 'ecommerce', 'label' => 'Xero Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'tenant_id' => 'secret',
        ]],
        'sage' => ['category' => 'ecommerce', 'label' => 'Sage Settings', 'fields' => [
            'client_id' => 'secret',
            'client_secret' => 'secret',
            'refresh_token' => 'secret',
            'accounting_endpoint' => 'string',
        ]],

        // -------------------------------------------------------------
        // Email & marketing
        // -------------------------------------------------------------
        'mailchimp' => ['category' => 'marketing', 'label' => 'Mailchimp Settings', 'fields' => [
            'api_key' => 'secret',
            'server_prefix' => 'string',
            'audience_id' => 'string',
        ]],
        'sendinblue' => ['category' => 'marketing', 'label' => 'Sendinblue Settings', 'fields' => [
            'api_key' => 'secret',
        ]],

        // -------------------------------------------------------------
        // E-invoicing
        // -------------------------------------------------------------
        'einvoice' => ['category' => 'einvoice', 'label' => 'E-Invoice Settings', 'fields' => [
            'electronic_address' => 'string',
            'company_id' => 'string',
            'software_id' => 'string',
            'software_name' => 'string',
        ]],
    ],
];
