<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function gql(string $query, array $variables = [])
{
    return test()->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
}

function grantGraphqlPermission(User $user, string $permission): void
{
    Permission::firstOrCreate([
        'name' => $permission,
        'guard_name' => PermissionGuard::name(),
    ]);

    $user->givePermissionTo($permission);
}

it('resolves the authenticated user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Passport::actingAs($user);

    gql('{ me { id name email company_id } }')
        ->assertOk()
        ->assertJsonPath('data.me.id', (string) $user->id)
        ->assertJsonPath('data.me.email', $user->email)
        ->assertJsonPath('data.me.company_id', (string) $company->id);
});

it('rejects unauthenticated access to guarded fields', function () {
    $response = gql('{ invoices { data { id } } }')->assertOk();

    expect($response->json('errors'))->not->toBeEmpty()
        ->and($response->json('data.invoices'))->toBeNull();
});

it('lists only the acting company invoices when permitted', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Passport::actingAs($user);
    grantGraphqlPermission($user, 'invoices.view-any');

    $mine = Invoice::factory()->create(['company_id' => $company->id]);
    Invoice::factory()->create(['company_id' => Company::factory()->create()->id]);

    gql('{ invoices { data { id invoice_number status } paginatorInfo { total } } }')
        ->assertOk()
        ->assertJsonPath('data.invoices.paginatorInfo.total', 1)
        ->assertJsonPath('data.invoices.data.0.id', (string) $mine->id)
        ->assertJsonPath('data.invoices.data.0.invoice_number', $mine->invoice_number);
});

it('blocks fields the user lacks permission for', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Passport::actingAs($user);
    grantGraphqlPermission($user, 'invoices.view-any');

    $response = gql('{ products { data { id } } }')->assertOk();

    expect($response->json('errors'))->not->toBeEmpty()
        ->and($response->json('data.products'))->toBeNull();
});

it('filters invoices by status', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Passport::actingAs($user);
    grantGraphqlPermission($user, 'invoices.view-any');

    Invoice::factory()->create(['company_id' => $company->id, 'status' => 'paid']);
    $overdue = Invoice::factory()->create(['company_id' => $company->id, 'status' => 'overdue']);

    gql('query ($status: String) { invoices(status: $status) { data { id } paginatorInfo { total } } }', ['status' => 'overdue'])
        ->assertOk()
        ->assertJsonPath('data.invoices.paginatorInfo.total', 1)
        ->assertJsonPath('data.invoices.data.0.id', (string) $overdue->id);
});
