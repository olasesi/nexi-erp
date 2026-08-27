<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', "Bearer $this->token");
});

it('lists contacts', function () {
    Contact::factory(3)->create();

    $response = $this->getJson('/api/v1/contacts');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a contact', function () {
    $contact = Contact::factory()->create();

    $response = $this->getJson("/api/v1/contacts/{$contact->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $contact->id);
});

it('creates a contact', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/contacts', [
        'company_id' => $company->id,
        'type' => 'customer',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.first_name', 'John');
});

it('updates a contact', function () {
    $contact = Contact::factory()->create();

    $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
        'first_name' => 'Jane',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.first_name', 'Jane');
});

it('deletes a contact', function () {
    $contact = Contact::factory()->create();

    $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($contact);
});

it('filters contacts by type', function () {
    Contact::factory()->customer()->create();
    Contact::factory()->supplier()->create();

    $response = $this->getJson('/api/v1/contacts?type=customer');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('type'))->each->toBe('customer');
});

it('searches contacts by name', function () {
    Contact::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
    Contact::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

    $response = $this->getJson('/api/v1/contacts?search=Alice');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->flushHeaders();

    $this->getJson('/api/v1/contacts')->assertUnauthorized();
});
