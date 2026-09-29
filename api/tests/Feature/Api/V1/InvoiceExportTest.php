<?php

use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'customer@example.com',
    ]);
    $this->invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 100,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'discount_rate' => 0,
        'total' => 100,
        'paid_amount' => 0,
        'balance_due' => 100,
        'payment_token' => 'abcdef0123456789abcdef0123456789abcdef01',
    ]);
});

it('downloads an invoice as a pdf', function () {
    $this->getJson("/api/v1/invoices/{$this->invoice->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('emails the invoice with a pdf attachment', function () {
    Mail::fake();

    $this->postJson("/api/v1/invoices/{$this->invoice->id}/email")
        ->assertOk()
        ->assertJsonPath('message', 'Invoice emailed to customer@example.com.');

    Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
        return $mail->hasTo('customer@example.com')
            && $mail->hasSubject('Invoice '.$this->invoice->invoice_number);
    });
});

it('does not email an invoice whose contact has no email address', function () {
    Mail::fake();

    $this->contact->update(['email' => null]);

    $this->postJson("/api/v1/invoices/{$this->invoice->id}/email")->assertStatus(422);

    Mail::assertNothingSent();
});

it('exposes the payment link through the invoice resource', function () {
    $this->getJson("/api/v1/invoices/{$this->invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_link', url('/api/v1/public/invoices/pay/'.$this->invoice->payment_token));
});

it('shows the payable invoice from the public payment link', function () {
    $this->getJson('/api/v1/public/invoices/pay/'.$this->invoice->payment_token)
        ->assertOk()
        ->assertJsonPath('data.invoice_number', $this->invoice->invoice_number)
        ->assertJsonPath('data.total', 100)
        ->assertJsonPath('data.balance_due', 100);
});

it('records a payment through the public payment link', function () {
    $this->postJson('/api/v1/public/invoices/pay/'.$this->invoice->payment_token, ['amount' => 60])
        ->assertCreated()
        ->assertJsonPath('data.balance_due', 40);

    $payment = Payment::query()
        ->where('invoice_id', $this->invoice->id)
        ->where('method', 'payment_link')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('completed')
        ->and($payment->amount)->toBe(60.0);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => 'partial',
        'paid_amount' => 60,
        'balance_due' => 40,
    ]);
});

it('marks an invoice paid when the public link covers the balance', function () {
    $this->postJson('/api/v1/public/invoices/pay/'.$this->invoice->payment_token)
        ->assertCreated()
        ->assertJsonPath('data.balance_due', 0)
        ->assertJsonPath('data.status', 'paid');
});

it('rejects overpayment on the public payment link', function () {
    $this->postJson('/api/v1/public/invoices/pay/'.$this->invoice->payment_token, ['amount' => 150])
        ->assertStatus(422);
});

it('rejects an unknown payment token', function () {
    $this->getJson('/api/v1/public/invoices/pay/invalid-token')->assertNotFound();
});

it('rejects payment for a draft invoice through the link', function () {
    $this->invoice->update(['status' => 'draft']);

    $this->postJson('/api/v1/public/invoices/pay/'.$this->invoice->payment_token)
        ->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('does not expose a payment link for bills', function () {
    $bill = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'type' => 'bill',
        'status' => 'sent',
        'subtotal' => 50,
        'tax_amount' => 0,
        'total' => 50,
        'paid_amount' => 0,
        'balance_due' => 50,
        'payment_token' => null,
    ]);

    $this->getJson("/api/v1/invoices/{$bill->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_link', null);

    $this->getJson('/api/v1/public/invoices/pay/invalid-token')->assertNotFound();
});
