<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('creates a webhook endpoint with a generated secret', function () {
    $response = $this->postJson('/api/v1/webhook-endpoints', [
        'name' => 'Finance systems',
        'url' => 'https://hooks.example.com/nexi',
        'events' => ['invoice.created', 'payment.completed'],
        'description' => 'Posts invoices into the finance system.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Finance systems')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.events', ['invoice.created', 'payment.completed']);

    expect($response->json('data.secret'))->toHaveLength(48);

    $this->assertDatabaseHas('webhook_endpoints', [
        'name' => 'Finance systems',
        'url' => 'https://hooks.example.com/nexi',
    ]);
});

it('rejects unknown events and non https urls', function () {
    $this->postJson('/api/v1/webhook-endpoints', [
        'name' => 'Bad endpoint',
        'url' => 'http://example.com/hook',
        'events' => ['invoice.exploded'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['url', 'events.0']);
});

it('allows plain http for loopback endpoints', function () {
    $this->postJson('/api/v1/webhook-endpoints', [
        'name' => 'Local relay',
        'url' => 'http://localhost:8080/hook',
        'events' => ['invoice.created'],
    ])->assertCreated();
});

it('exposes the subscribable event catalogue', function () {
    $response = $this->getJson('/api/v1/webhook-events')->assertOk();

    $events = array_column($response->json('data'), 'name');

    expect($events)->toContain('invoice.created', 'payment.completed', 'contact.updated', 'invoice.restored');
});

it('lists, shows, updates and deletes endpoints', function () {
    $endpoint = WebhookEndpoint::factory()->create(['company_id' => null, 'name' => 'Ops']);

    $this->getJson('/api/v1/webhook-endpoints')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ops');

    $this->getJson("/api/v1/webhook-endpoints/{$endpoint->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $endpoint->id);

    $this->putJson("/api/v1/webhook-endpoints/{$endpoint->id}", [
        'events' => ['product.created'],
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.events', ['product.created']);

    $this->deleteJson("/api/v1/webhook-endpoints/{$endpoint->id}")->assertNoContent();

    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
});

it('delivers a signed payload when a subscribed model changes', function () {
    Http::fake();

    $company = Company::factory()->create();

    $endpoint = WebhookEndpoint::factory()->create([
        'company_id' => $company->id,
        'events' => ['invoice.created'],
        'url' => 'https://hooks.example.com/nexi',
    ]);

    $invoice = Invoice::factory()->create(['company_id' => $company->id]);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($endpoint, $invoice): bool {
        $payload = json_decode($request->body(), true);

        expect($payload['event'])->toBe('invoice.created')
            ->and($payload['data']['id'])->toBe($invoice->id)
            ->and($payload['data']['type'])->toBe($invoice->getMorphClass())
            ->and($payload['data']['attributes']['invoice_number'])->toBe($invoice->invoice_number)
            ->and($request->header('X-Nexi-Event')[0])->toBe('invoice.created');

        $timestamp = (int) $request->header('X-Nexi-Timestamp')[0];

        return WebhookService::verifySignature(
            $endpoint->secret,
            $timestamp,
            $request->body(),
            $request->header('X-Nexi-Signature')[0],
        );
    });

    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_endpoint_id' => $endpoint->id,
        'event' => 'invoice.created',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
        'response_status' => 200,
        'attempts' => 1,
    ]);

    expect($endpoint->refresh()->last_triggered_at)->not->toBeNull();
});

it('never leaks sensitive attributes in payloads', function () {
    Http::fake();

    $endpoint = WebhookEndpoint::factory()->create(['events' => ['user.created']]);

    User::factory()->create(['name' => 'Grace Hopper', 'password' => 'super-secret']);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode($request->body(), true);

        expect($payload['data']['attributes']['name'])->toBe('Grace Hopper')
            ->and($payload['data']['attributes'])->not->toHaveKey('password')
            ->and($payload['data']['attributes'])->not->toHaveKey('remember_token');

        return true;
    });

    expect($endpoint->id)->toBeInt();
});

it('skips endpoints that are inactive or not subscribed', function () {
    Http::fake();

    WebhookEndpoint::factory()->inactive()->create(['events' => ['invoice.created']]);
    WebhookEndpoint::factory()->create(['events' => ['payment.created']]);

    Invoice::factory()->create();
    Contact::factory()->create();

    Http::assertNothingSent();

    $this->assertDatabaseCount('webhook_deliveries', 0);
});

it('only delivers to endpoints of the same company plus global ones', function () {
    Http::fake();

    $otherCompanyEndpoint = WebhookEndpoint::factory()->create(['events' => ['invoice.created']]);
    $globalEndpoint = WebhookEndpoint::factory()->create(['events' => ['invoice.created'], 'company_id' => null]);

    $invoice = Invoice::factory()->create();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($globalEndpoint): bool {
        return $request->url() === $globalEndpoint->url;
    });

    expect($otherCompanyEndpoint->deliveries()->count())->toBe(0);
    expect($invoice->company_id)->not->toBeNull();
});

it('records a failed delivery and schedules a retry', function () {
    Http::fake(['*' => Http::response('upstream exploded', 500)]);

    $company = Company::factory()->create();

    $endpoint = WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['invoice.created']]);

    Invoice::factory()->create(['company_id' => $company->id]);

    $delivery = WebhookDelivery::query()->firstOrFail();

    expect($delivery->status)->toBe(WebhookDelivery::STATUS_PENDING)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->response_status)->toBe(500)
        ->and($delivery->response_body)->toBe('upstream exploded')
        ->and($delivery->last_error)->toContain('HTTP 500')
        ->and($delivery->next_attempt_at)->not->toBeNull()
        ->and($delivery->delivered_at)->toBeNull();

    expect($endpoint->id)->toBeInt();
});

it('marks a delivery failed once the attempt budget is exhausted', function () {
    config(['webhooks.max_attempts' => 1]);

    Http::fake(['*' => Http::response('nope', 503)]);

    $company = Company::factory()->create();

    WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['invoice.created']]);

    Invoice::factory()->create(['company_id' => $company->id]);

    expect(WebhookDelivery::query()->firstOrFail()->status)->toBe(WebhookDelivery::STATUS_FAILED);
});

it('replays a failed delivery and refuses to replay a successful one', function () {
    Http::fakeSequence()->push('boom', 500)->push(['ok' => true], 200);

    $company = Company::factory()->create();

    $endpoint = WebhookEndpoint::factory()->create(['company_id' => $company->id, 'events' => ['invoice.created']]);

    Invoice::factory()->create(['company_id' => $company->id]);

    $delivery = WebhookDelivery::query()->firstOrFail();

    expect($delivery->status)->toBe(WebhookDelivery::STATUS_PENDING);

    $this->postJson("/api/v1/webhook-deliveries/{$delivery->id}/redeliver")
        ->assertOk()
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_SUCCEEDED)
        ->assertJsonPath('data.attempts', 2);

    $this->postJson("/api/v1/webhook-deliveries/{$delivery->id}/redeliver")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This delivery has already succeeded.');

    expect($endpoint->deliveries()->count())->toBe(1);
});

it('sends a test payload without touching the event catalogue', function () {
    Http::fake();

    $endpoint = WebhookEndpoint::factory()->create(['events' => ['invoice.created']]);

    $this->postJson("/api/v1/webhook-endpoints/{$endpoint->id}/test")
        ->assertCreated()
        ->assertJsonPath('data.event', 'webhook.test')
        ->assertJsonPath('data.status', WebhookDelivery::STATUS_SUCCEEDED);

    Http::assertSent(function (Request $request): bool {
        $payload = json_decode($request->body(), true);

        return $request->header('X-Nexi-Event')[0] === 'webhook.test'
            && $payload['data']['attributes']['message'] === 'This is a test delivery from Nexi ERP.';
    });
});

it('rotates the signing secret', function () {
    $endpoint = WebhookEndpoint::factory()->create();

    $response = $this->postJson("/api/v1/webhook-endpoints/{$endpoint->id}/rotate-secret")->assertOk();

    expect($response->json('data.secret'))->not->toBe($endpoint->secret)
        ->and($response->json('data.secret'))->toHaveLength(48);
});

it('lists deliveries for an endpoint and across endpoints', function () {
    $endpoint = WebhookEndpoint::factory()->create();
    $succeeded = WebhookDelivery::factory()->succeeded()->create(['webhook_endpoint_id' => $endpoint->id]);
    $pending = WebhookDelivery::factory()->create(['webhook_endpoint_id' => $endpoint->id]);
    WebhookDelivery::factory()->create();

    $this->getJson("/api/v1/webhook-endpoints/{$endpoint->id}/deliveries")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $pending->id);

    $this->getJson("/api/v1/webhook-endpoints/{$endpoint->id}/deliveries?status=succeeded")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $succeeded->id);

    $this->getJson('/api/v1/webhook-deliveries')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('rejects a tampered or stale signature', function () {
    $secret = 'shared-secret';
    $timestamp = now()->getTimestamp();
    $body = json_encode(['event' => 'invoice.created']);

    expect(WebhookService::verifySignature($secret, $timestamp, $body, WebhookService::signature($secret, $timestamp, $body)))->toBeTrue()
        ->and(WebhookService::verifySignature($secret, $timestamp, $body, 'sha256=deadbeef'))->toBeFalse()
        ->and(WebhookService::verifySignature('other', $timestamp, $body, WebhookService::signature($secret, $timestamp, $body)))->toBeFalse()
        ->and(WebhookService::verifySignature($secret, $timestamp - 4000, $body, WebhookService::signature($secret, $timestamp - 4000, $body)))->toBeFalse();
});
