<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();

    $this->seed(RolesAndPermissionsSeeder::class);

    $client = Client::factory()->asPasswordClient()->create([
        'redirect' => 'http://localhost',
        'secret' => 'test-secret',
    ]);

    config([
        'services.passport.password_client_id' => $client->id,
        'services.passport.password_client_secret' => 'test-secret',
    ]);
});

function customerRegistrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

it('registers a customer whose email matches an active customer contact and returns a token', function () {
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/customer/register', customerRegistrationData());

    $response->assertCreated()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token', 'user'])
        ->assertJsonPath('access_token', fn ($value) => is_string($value) && strlen($value) > 20);

    $user = User::where('email', 'ada@acme.test')->firstOrFail();
    expect($user->contact_id)->toBe($contact->id)
        ->and($user->company_id)->toBe($this->company->id)
        ->and($user->hasRole('customer'))->toBeTrue()
        ->and($user->hasPermissionTo('portal.access'))->toBeTrue();
});

it('rejects registration when the email does not match an active customer contact', function () {
    Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'someone-else@acme.test',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/customer/register', customerRegistrationData())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(User::where('email', 'ada@acme.test')->exists())->toBeFalse();
});

it('rejects registration when the email matches an inactive customer contact', function () {
    Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/customer/register', customerRegistrationData())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects registration when the contact already has a portal account', function () {
    Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/customer/register', customerRegistrationData())->assertCreated();

    $this->postJson('/api/v1/customer/register', customerRegistrationData())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('lets a customer log in through the standard login endpoint', function () {
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ]);
    $customer->assignRole('customer');

    $this->postJson('/api/v1/login', [
        'email' => 'ada@acme.test',
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token', 'user']);
});

it('exposes the customer profile through the portal me endpoint', function () {
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
    ]);
    Passport::actingAs($customer);

    $this->getJson('/api/v1/customer/me')
        ->assertOk()
        ->assertJsonPath('contact.id', $contact->id)
        ->assertJsonPath('contact.type', 'customer')
        ->assertJsonPath('company_id', $this->company->id);
});

it('returns only the customer’s own invoices in the portal', function () {
    $other = Contact::factory()->customer()->create(['company_id' => $this->company->id]);
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    Invoice::factory()->create(['company_id' => $this->company->id, 'contact_id' => $contact->id]);
    Invoice::factory()->create(['company_id' => $this->company->id, 'contact_id' => $other->id]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
    ]);
    Passport::actingAs($customer);

    $response = $this->getJson('/api/v1/customer/invoices')->assertOk();

    expect(collect($response->json('data'))->pluck('contact_id')->all())->toBe([$contact->id]);
});

it('lets a customer open one of their own invoices but not another customer’s', function () {
    $other = Contact::factory()->customer()->create(['company_id' => $this->company->id]);
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    $own = Invoice::factory()->create(['company_id' => $this->company->id, 'contact_id' => $contact->id]);
    $foreign = Invoice::factory()->create(['company_id' => $this->company->id, 'contact_id' => $other->id]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
    ]);
    Passport::actingAs($customer);

    $this->getJson("/api/v1/customer/invoices/{$own->id}")->assertOk();
    $this->getJson("/api/v1/customer/invoices/{$foreign->id}")->assertNotFound();
});

it('also exposes quotations, sales orders and payments scoped to the customer', function () {
    $other = Contact::factory()->customer()->create(['company_id' => $this->company->id]);
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    Quotation::factory()->create(['company_id' => $this->company->id, 'contact_id' => $contact->id]);
    SalesOrder::factory()->create(['company_id' => $this->company->id, 'contact_id' => $contact->id]);
    SalesOrder::factory()->create(['company_id' => $this->company->id, 'contact_id' => $other->id]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
    ]);
    Passport::actingAs($customer);

    $this->getJson('/api/v1/customer/quotations')->assertOk();
    $this->getJson('/api/v1/customer/sales-orders')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.contact_id', $contact->id);
    $this->getJson('/api/v1/customer/payments')->assertOk();
});

it('blocks customers from the staff-facing API surface', function () {
    $contact = Contact::factory()->customer()->create([
        'company_id' => $this->company->id,
        'email' => 'ada@acme.test',
        'is_active' => true,
    ]);

    $customer = User::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'email' => 'ada@acme.test',
    ]);
    Passport::actingAs($customer);

    $this->getJson('/api/v1/contacts')->assertForbidden();
    $this->getJson('/api/v1/invoices')->assertForbidden();
    $this->getJson('/api/v1/settings')->assertForbidden();
});

it('blocks staff users from the customer portal', function () {
    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/customer/me')->assertForbidden();
});

it('requires authentication for the customer portal', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/customer/invoices')->assertUnauthorized();
});
