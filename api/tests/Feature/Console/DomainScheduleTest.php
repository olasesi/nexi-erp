<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('marks past-due invoices overdue and notifies the company', function () {
    $company = Company::factory()->create();

    $overdue = Invoice::factory()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'due_date' => now()->subDays(3)->toDateString(),
        'balance_due' => 250,
    ]);

    $future = Invoice::factory()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'due_date' => now()->addDays(5)->toDateString(),
        'balance_due' => 100,
    ]);

    $paid = Invoice::factory()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'status' => 'paid',
        'due_date' => now()->subDays(9)->toDateString(),
        'balance_due' => 0,
    ]);

    $this->artisan('invoices:mark-overdue')
        ->expectsOutputToContain('Marked 1 invoice(s) overdue.')
        ->assertSuccessful();

    expect($overdue->fresh()->status)->toBe('overdue')
        ->and($future->fresh()->status)->toBe('sent')
        ->and($paid->fresh()->status)->toBe('paid');

    $notification = Notification::where('type', 'invoice.overdue')->sole();

    expect($notification->company_id)->toBe($company->id)
        ->and($notification->user_id)->toBeNull()
        ->and($notification->data['invoice_id'])->toBe($overdue->id);
});

it('is idempotent across runs', function () {
    $company = Company::factory()->create();

    Invoice::factory()->create([
        'company_id' => $company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'due_date' => now()->subDay()->toDateString(),
        'balance_due' => 80,
    ]);

    $this->artisan('invoices:mark-overdue')->assertSuccessful();
    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect(Notification::where('type', 'invoice.overdue')->count())->toBe(1);
});

it('raises low-stock alerts for products at or below the reorder level', function () {
    $company = Company::factory()->create();

    $low = Product::factory()->create([
        'company_id' => $company->id,
        'stock_quantity' => 2,
        'min_stock_level' => 5,
        'is_active' => true,
    ]);

    $healthy = Product::factory()->create([
        'company_id' => $company->id,
        'stock_quantity' => 40,
        'min_stock_level' => 5,
        'is_active' => true,
    ]);

    $inactive = Product::factory()->create([
        'company_id' => $company->id,
        'stock_quantity' => 1,
        'min_stock_level' => 5,
        'is_active' => false,
    ]);

    $this->artisan('stock:alerts')
        ->expectsOutputToContain('Raised 1 low-stock alert(s).')
        ->assertSuccessful();

    $notification = Notification::where('type', 'stock.low')->sole();

    expect($notification->company_id)->toBe($company->id)
        ->and($notification->data['product_id'])->toBe($low->id);

    expect(Notification::where('type', 'stock.low')->where('company_id', $healthy->company_id)->count())->toBe(1);
});

it('can be limited to a single company', function () {
    $one = Company::factory()->create();
    $two = Company::factory()->create();

    Invoice::factory()->create(['company_id' => $one->id, 'type' => 'invoice', 'status' => 'sent', 'due_date' => now()->subDay()->toDateString(), 'balance_due' => 10]);
    Invoice::factory()->create(['company_id' => $two->id, 'type' => 'invoice', 'status' => 'sent', 'due_date' => now()->subDay()->toDateString(), 'balance_due' => 10]);

    $this->artisan('invoices:mark-overdue', ['--company' => $one->id])->assertSuccessful();

    expect(Notification::where('type', 'invoice.overdue')->count())->toBe(1)
        ->and(Notification::first()->company_id)->toBe($one->id);
});

it('registers the domain commands on the scheduler', function () {
    Artisan::call('schedule:list');
    $list = Artisan::output();

    expect($list)->toContain('invoices:mark-overdue')
        ->and($list)->toContain('stock:alerts');
});
