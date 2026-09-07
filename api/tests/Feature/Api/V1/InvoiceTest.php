<?php

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\GeneralLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
});

it('creates an invoice with computed totals', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'contact_id' => $this->contact->id,
        'issue_date' => '2026-09-01',
        'items' => [
            ['description' => 'Product A', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 10],
            ['description' => 'Product B', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
        ],
    ])->assertCreated()->assertJsonPath('data.status', 'draft');

    $invoice = Invoice::first();

    expect($invoice->subtotal)->toEqual(250)
        ->and($invoice->tax_amount)->toEqual(20)
        ->and($invoice->total)->toEqual(270)
        ->and($invoice->balance_due)->toEqual(270)
        ->and($invoice->items()->count())->toBe(2);

    // Draft invoices do not touch the general ledger yet.
    expect(JournalEntry::count())->toBe(0);
});

it('posts a journal entry when an invoice is issued', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'draft',
        'subtotal' => 250,
        'tax_amount' => 20,
        'total' => 270,
    ]);

    $this->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'sent'])->assertOk();

    $entry = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->first();

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe('posted')
        ->and($entry->lines->sum('debit'))->toEqual(270)
        ->and($entry->lines->sum('credit'))->toEqual(270);

    $this->assertDatabaseHas('journal_entry_lines', [
        'journal_entry_id' => $entry->id,
        'debit' => 270,
    ]);
});

it('voids the journal entry when an invoice is cancelled', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 250,
        'tax_amount' => 20,
        'total' => 270,
    ]);

    $this->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'sent'])->assertOk();

    expect(JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->first()->status)->toBe('posted');

    $this->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'cancelled'])->assertOk();

    expect(JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->first()->status)->toBe('void');
});

it('updates paid amount and status from completed payments', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'contact_id' => $this->contact->id,
        'subtotal' => 100,
        'tax_amount' => 10,
        'total' => 110,
        'paid_amount' => 0,
        'balance_due' => 110,
    ]);

    $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-09-02',
        'amount' => 50,
        'status' => 'completed',
    ])->assertCreated();

    $invoice->refresh();

    expect($invoice->paid_amount)->toEqual(50)
        ->and($invoice->balance_due)->toEqual(60)
        ->and($invoice->status)->toBe('partial');

    $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-09-03',
        'amount' => 60,
        'status' => 'completed',
    ])->assertCreated();

    expect($invoice->refresh()->status)->toBe('paid')
        ->and($invoice->refresh()->balance_due)->toEqual(0);
});

it('enforces default chart of accounts on posting', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'draft',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $this->putJson("/api/v1/invoices/{$invoice->id}", ['status' => 'sent'])->assertOk();

    expect(ChartOfAccount::where('company_id', $this->company->id)->count())
        ->toBe(count(GeneralLedgerService::DEFAULT_ACCOUNTS));
});

it('rejects deleting invoices with completed payments', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'contact_id' => $this->contact->id,
        'subtotal' => 100,
        'total' => 100,
    ]);

    Payment::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'invoice_id' => $invoice->id,
        'status' => 'completed',
        'amount' => 100,
    ]);

    $this->deleteJson("/api/v1/invoices/{$invoice->id}")->assertStatus(422);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/invoices')->assertUnauthorized();
});
