<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

beforeEach(function () {
    $client = Client::factory()->asPasswordClient()->create([
        'redirect' => 'http://localhost',
        'secret' => 'test-secret',
    ]);

    config([
        'services.passport.password_client_id' => $client->id,
        'services.passport.password_client_secret' => 'test-secret',
    ]);

    User::factory()->create([
        'email' => 'admin@nexi-corp.com',
        'password' => 'password',
    ]);
});

it('counts successful logins in the metrics exposition', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'admin@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_auth_logins_total 1', false);
});

it('counts failed logins in the metrics exposition', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'admin@nexi-corp.com',
        'password' => 'wrong',
    ])->assertUnprocessable();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_auth_login_failures_total 1', false);
});

it('does not count a failed login as a success', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'admin@nexi-corp.com',
        'password' => 'wrong',
    ])->assertUnprocessable();

    $metrics = $this->get('/api/metrics')->assertOk()->getContent();

    expect($metrics)->toContain('nexi_erp_auth_logins_total 0')
        ->and($metrics)->toContain('nexi_erp_auth_login_failures_total 1');
});

it('counts staff registrations in the metrics exposition', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'New Staff',
        'email' => 'staff@nexi-corp.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertOk();

    $response->assertJsonStructure(['access_token']);

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_auth_registrations_total 1', false);
});

it('counts customer portal registrations in the metrics exposition', function () {
    Contact::factory()->customer()->create([
        'company_id' => Company::factory()->create()->id,
        'email' => 'customer@nexi-corp.com',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/customer/register', [
        'name' => 'Portal Customer',
        'email' => 'customer@nexi-corp.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_auth_registrations_total 1', false);
});

it('counts logins with the database cache store (production driver)', function () {
    $this->app['config']->set('cache.default', 'database');

    $this->postJson('/api/v1/login', [
        'email' => 'admin@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_auth_logins_total 1', false);
});
