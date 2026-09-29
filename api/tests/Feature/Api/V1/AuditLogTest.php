<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('records a created event when an invoice is created', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'invoice_number' => 'INV-9001',
        'status' => 'draft',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'event' => 'created',
        'auditable_type' => Invoice::class,
        'auditable_id' => $invoice->id,
    ]);

    $log = AuditLog::where('auditable_type', Invoice::class)
        ->where('auditable_id', $invoice->id)
        ->where('event', 'created')
        ->first();

    expect($log->new_values['invoice_number'])->toBe('INV-9001');
});

it('records an updated event with the previous and new values', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'invoice_number' => 'INV-9002',
        'total' => 100,
    ]);

    $invoice->update(['total' => 250, 'notes' => 'Revised']);

    $log = AuditLog::where('auditable_type', Invoice::class)
        ->where('auditable_id', $invoice->id)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->old_values['total'])->toBe(100)
        ->and($log->new_values['total'])->toBe(250);
});

it('records a deleted event', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'invoice_number' => 'INV-9003',
    ]);

    $invoice->delete();

    $this->assertDatabaseHas('audit_logs', [
        'company_id' => $this->company->id,
        'event' => 'deleted',
        'auditable_id' => $invoice->id,
    ]);
});

it('never persists sensitive user attributes to the trail', function () {
    $created = User::factory()->create();
    $created->update(['name' => 'Renamed']);

    $logs = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $created->id)
        ->get();

    expect($logs)->not->toBeEmpty();

    foreach ($logs as $log) {
        expect($log->old_values)
            ->not->toHaveKey('password')
            ->and($log->new_values)
            ->not->toHaveKey('password')
            ->and($log->new_values)
            ->not->toHaveKey('remember_token');
    }
});

it('lists audit trail entries', function () {
    Invoice::factory()->create(['company_id' => $this->company->id]);
    Invoice::factory()->create(['company_id' => $this->company->id]);

    $auditableType = urlencode(Invoice::class);

    $response = $this->getJson('/api/v1/audit-logs?event=created&auditable_type='.$auditableType)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.event'))->toBe('created')
        ->and($response->json('data.0.auditable_type'))->toBe(Invoice::class);
});

it('shows a single audit entry', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'invoice_number' => 'INV-9009',
    ]);

    $log = AuditLog::where('auditable_type', Invoice::class)
        ->where('auditable_id', $invoice->id)
        ->first();

    $this->getJson('/api/v1/audit-logs/'.$log->id)
        ->assertOk()
        ->assertJsonPath('data.event', 'created')
        ->assertJsonPath('data.auditable_id', $invoice->id);
});
