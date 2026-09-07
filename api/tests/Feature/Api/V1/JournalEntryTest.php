<?php

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();

    $this->accounts = [];
    foreach (['1010' => 'Cash', '4010' => 'Sales Revenue'] as $code => $name) {
        $this->accounts[$code] = ChartOfAccount::factory()->create([
            'company_id' => $this->company->id,
            'code' => $code,
            'name' => $name,
        ]);
    }
});

it('posts a balanced journal entry via the API', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'company_id' => $this->company->id,
        'entry_date' => '2026-09-01',
        'description' => 'Sale',
        'status' => 'posted',
        'lines' => [
            ['chart_of_account_id' => $this->accounts['1010']->id, 'debit' => 100],
            ['chart_of_account_id' => $this->accounts['4010']->id, 'credit' => 100],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.total_debit', 100);

    expect(JournalEntry::count())->toBe(1);
});

it('rejects an unbalanced journal entry', function () {
    $this->postJson('/api/v1/journal-entries', [
        'company_id' => $this->company->id,
        'entry_date' => '2026-09-01',
        'lines' => [
            ['chart_of_account_id' => $this->accounts['1010']->id, 'debit' => 100],
            ['chart_of_account_id' => $this->accounts['4010']->id, 'credit' => 90],
        ],
    ])->assertStatus(422);
});

it('creates a draft then posts it', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'company_id' => $this->company->id,
        'entry_date' => '2026-09-01',
        'status' => 'draft',
        'lines' => [
            ['chart_of_account_id' => $this->accounts['1010']->id, 'debit' => 50],
            ['chart_of_account_id' => $this->accounts['4010']->id, 'credit' => 50],
        ],
    ])->assertCreated();

    $id = $response->json('data.id');

    $this->postJson("/api/v1/journal-entries/{$id}/post")
        ->assertOk()
        ->assertJsonPath('data.status', 'posted');
});

it('cannot edit a posted entry', function () {
    $entry = JournalEntry::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'posted',
    ]);

    $this->putJson("/api/v1/journal-entries/{$entry->id}", ['description' => 'nope'])
        ->assertStatus(422);
});

it('voids a posted entry', function () {
    $response = $this->postJson('/api/v1/journal-entries', [
        'company_id' => $this->company->id,
        'entry_date' => '2026-09-01',
        'lines' => [
            ['chart_of_account_id' => $this->accounts['1010']->id, 'debit' => 10],
            ['chart_of_account_id' => $this->accounts['4010']->id, 'credit' => 10],
        ],
    ])->assertCreated();

    $this->postJson('/api/v1/journal-entries/'.$response->json('data.id').'/void')
        ->assertOk()
        ->assertJsonPath('data.status', 'void');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/journal-entries')->assertUnauthorized();
});
