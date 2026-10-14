<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\BusinessSettingsService;
use App\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
});

function payableInvoice(array $attributes = []): Invoice
{
    return Invoice::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'contact_id' => test()->contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 100,
        'paid_amount' => 0,
        'balance_due' => 100,
        'payment_token' => 'tok_'.Str::random(24),
    ], $attributes));
}

function connectStripe(int $companyId): void
{
    app(BusinessSettingsService::class)->set($companyId, 'stripe', [
        'publishable_key' => 'pk_test_123',
        'secret_key' => 'sk_test_123',
        'webhook_secret' => 'whsec_test',
    ]);

    app(IntegrationService::class)->connect($companyId, 'stripe');
}

function stripeSignature(string $payload, string $secret = 'whsec_test'): string
{
    $timestamp = time();

    return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
}

it('settles a checkout inline through the stub gateway', function () {
    $invoice = payableInvoice();

    $this->postJson("/api/v1/public/invoices/pay/{$invoice->payment_token}/checkout")
        ->assertCreated()
        ->assertJsonPath('data.provider', 'stub')
        ->assertJsonPath('data.completed', true);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and((float) $invoice->fresh()->balance_due)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('still records a payment through the manual pay link', function () {
    $invoice = payableInvoice();

    $this->postJson("/api/v1/public/invoices/pay/{$invoice->payment_token}", ['amount' => 40])
        ->assertCreated()
        ->assertJsonPath('data.payment_number', fn ($value) => is_string($value));

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and((float) $invoice->fresh()->balance_due)->toBe(60.0);
});

it('creates a Stripe checkout session and returns the redirect url', function () {
    $invoice = payableInvoice();
    connectStripe($this->company->id);

    Http::fake([
        'api.stripe.com/*' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
        ], 200),
    ]);

    $this->postJson("/api/v1/public/invoices/pay/{$invoice->payment_token}/checkout")
        ->assertCreated()
        ->assertJsonPath('data.provider', 'stripe')
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123');

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(0);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $request->hasHeader('Authorization', 'Bearer sk_test_123'));
});

it('records a payment from a signed Stripe webhook', function () {
    $invoice = payableInvoice();
    connectStripe($this->company->id);

    $payload = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_123',
            'payment_status' => 'paid',
            'amount_total' => 10000,
            'currency' => 'usd',
            'client_reference_id' => $invoice->payment_token,
            'payment_intent' => 'pi_123',
        ]],
    ]);

    $this->call('POST', '/api/v1/public/payments/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)
        ->assertOk()
        ->assertJsonPath('handled', true);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and((float) $invoice->fresh()->balance_due)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('rejects a Stripe webhook with an invalid signature', function () {
    $invoice = payableInvoice();
    connectStripe($this->company->id);

    $payload = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['client_reference_id' => $invoice->payment_token]],
    ]);

    $this->call('POST', '/api/v1/public/payments/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'wrong_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('ignores a replayed Stripe webhook', function () {
    $invoice = payableInvoice();
    connectStripe($this->company->id);

    $payload = json_encode([
        'id' => 'evt_1',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_123',
            'payment_status' => 'paid',
            'amount_total' => 10000,
            'currency' => 'usd',
            'client_reference_id' => $invoice->payment_token,
            'payment_intent' => 'pi_123',
        ]],
    ]);

    $server = [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload),
        'CONTENT_TYPE' => 'application/json',
    ];

    $this->call('POST', '/api/v1/public/payments/webhooks/stripe', [], [], [], $server, $payload)->assertOk();
    $this->call('POST', '/api/v1/public/payments/webhooks/stripe', [], [], [], $server, $payload)
        ->assertOk()
        ->assertJsonPath('handled', true);

    expect(Payment::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('generates a shareable payment link for a sales invoice', function () {
    $user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($user);

    $invoice = payableInvoice(['payment_token' => null]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/payment-link")
        ->assertOk()
        ->assertJsonPath('data.payment_url', fn ($value) => is_string($value) && str_contains($value, '/public/invoices/pay/'));

    expect($invoice->fresh()->payment_token)->not->toBeNull();
});
