<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
});

it('creates a payment and posts the receipt to the ledger', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'type' => 'invoice',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 100,
    ]);

    $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-09-01',
        'amount' => 100,
        'status' => 'completed',
    ])->assertCreated()->assertJsonPath('data.status', 'completed');

    $entry = JournalEntry::where('source_type', Payment::class)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('posted');

    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $entry->id,
        'debit' => 100,
    ]);
});

it('does not post to the ledger for a pending payment', function () {
    $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'payment_date' => '2026-09-01',
        'amount' => 100,
        'status' => 'pending',
    ])->assertCreated();

    expect(JournalEntry::count())->toBe(0);
});

it('completes a payment and posts it', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'status' => 'pending',
        'amount' => 75,
    ]);

    $this->postJson("/api/v1/payments/{$payment->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(JournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->count())->toBe(1);
});

it('posts a payment (money out) to the ledger', function () {
    $bill = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'type' => 'bill',
        'subtotal' => 80,
        'tax_amount' => 0,
        'total' => 80,
        'balance_due' => 80,
    ]);

    $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'payment',
        'contact_id' => $this->contact->id,
        'invoice_id' => $bill->id,
        'payment_date' => '2026-09-02',
        'amount' => 80,
        'status' => 'completed',
    ])->assertCreated();

    $entry = JournalEntry::where('source_type', Payment::class)->first();

    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $entry->id,
        'debit' => 80,
        'credit' => 0,
    ]);
});

it('voids the ledger entry when payment is deleted', function () {
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'status' => 'completed',
        'amount' => 50,
    ]);

    app(AccountingService::class)->postPayment($payment);

    expect(JournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->first()->status)->toBe('posted');

    $this->deleteJson("/api/v1/payments/{$payment->id}")->assertNoContent();

    expect(JournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->first()->status)->toBe('void');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/payments')->assertUnauthorized();
});
