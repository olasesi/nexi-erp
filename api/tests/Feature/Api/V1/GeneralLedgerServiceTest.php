<?php

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Services\GeneralLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('installs default accounts and posts balanced entries', function () {
    $company = Company::factory()->create();

    $gl = app(GeneralLedgerService::class);
    $gl->ensureDefaultAccounts($company->id);

    expect(ChartOfAccount::where('company_id', $company->id)->count())->toBe(count(GeneralLedgerService::DEFAULT_ACCOUNTS));

    $entry = $gl->post(
        $company->id,
        now()->toDateString(),
        'Test sale',
        [
            ['account_code' => '1010', 'debit' => 120],
            ['account_code' => '4010', 'credit' => 100],
            ['account_code' => '2100', 'credit' => 20],
        ],
        $company
    );

    expect($entry->status)->toBe('posted')
        ->and($entry->lines->sum('debit'))->toEqual(120)
        ->and($entry->lines->sum('credit'))->toEqual(120);

    $gl->voidForSource($company);

    expect($entry->fresh()->status)->toBe('void');
});

it('rejects unbalanced entries', function () {
    $company = Company::factory()->create();

    app(GeneralLedgerService::class)->post(
        $company->id,
        now()->toDateString(),
        'Bad entry',
        [
            ['account_code' => '1010', 'debit' => 100],
            ['account_code' => '4010', 'credit' => 90],
        ]
    );
})->throws(InvalidArgumentException::class);

it('rejects lines that are both debit and credit', function () {
    $company = Company::factory()->create();

    app(GeneralLedgerService::class)->post(
        $company->id,
        now()->toDateString(),
        'Bad line',
        [
            ['account_code' => '1010', 'debit' => 10, 'credit' => 10],
        ]
    );
})->throws(InvalidArgumentException::class);
