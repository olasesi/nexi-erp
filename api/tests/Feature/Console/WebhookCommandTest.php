<?php

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('attempts deliveries whose backoff has elapsed', function () {
    Http::fake(['example.com/*' => Http::response(['ok' => true], 200)]);

    $delivery = WebhookDelivery::factory()->create([
        'attempts' => 1,
        'next_attempt_at' => now()->subMinute(),
    ]);

    $this->artisan('webhooks:retry')
        ->expectsOutputToContain('1 succeeded')
        ->assertSuccessful();

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDelivery::STATUS_SUCCEEDED)
        ->and($delivery->attempts)->toBe(2)
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($delivery->delivered_at)->not->toBeNull();

    Http::assertSentCount(1);
});

it('leaves deliveries that are not due yet alone', function () {
    Http::fake();

    $delivery = WebhookDelivery::factory()->create([
        'attempts' => 1,
        'next_attempt_at' => now()->addMinutes(5),
    ]);

    $this->artisan('webhooks:retry')
        ->expectsOutputToContain('Attempted 0')
        ->assertSuccessful();

    expect($delivery->refresh()->status)->toBe(WebhookDelivery::STATUS_PENDING)
        ->and($delivery->attempts)->toBe(1);

    Http::assertNothingSent();
});

it('reports deliveries that exhausted their attempt budget instead of resending them', function () {
    Http::fake();

    $delivery = WebhookDelivery::factory()->create([
        'attempts' => config('webhooks.max_attempts'),
        'next_attempt_at' => now()->subHour(),
    ]);

    $this->artisan('webhooks:retry')
        ->expectsOutputToContain('exhausted their attempt budget')
        ->assertSuccessful();

    expect($delivery->refresh()->attempts)->toBe((int) config('webhooks.max_attempts'));

    Http::assertNothingSent();
});

it('can be limited to a single endpoint and previewed without sending', function () {
    Http::fake();

    $first = WebhookEndpoint::factory()->create();
    $second = WebhookEndpoint::factory()->create();

    $target = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $first->getKey(),
        'next_attempt_at' => now()->subMinute(),
    ]);

    $other = WebhookDelivery::factory()->create([
        'webhook_endpoint_id' => $second->getKey(),
        'next_attempt_at' => now()->subMinute(),
    ]);

    Artisan::call('webhooks:retry', ['--dry-run' => true, '--endpoint' => $first->getKey()]);
    $preview = Artisan::output();

    expect($preview)->toContain('#'.$target->getKey())
        ->and($preview)->not->toContain('#'.$other->getKey());

    Http::assertNothingSent();

    $this->artisan('webhooks:retry --endpoint='.$first->getKey())->assertSuccessful();

    expect($target->refresh()->status)->toBe(WebhookDelivery::STATUS_SUCCEEDED)
        ->and($target->attempts)->toBe(1)
        ->and($other->refresh()->attempts)->toBe(0);

    Http::assertSentCount(1);
});

it('honours the per run limit', function () {
    Http::fake(['example.com/*' => Http::response([], 200)]);

    foreach (range(1, 3) as $ignored) {
        WebhookDelivery::factory()->create(['next_attempt_at' => now()->subMinute()]);
    }

    $this->artisan('webhooks:retry --limit=2')->assertSuccessful();

    Http::assertSentCount(2);
});

it('prunes finished deliveries that fell out of the retention window', function () {
    $old = WebhookDelivery::factory()->succeeded()->create(['created_at' => now()->subDays(90)]);
    $oldFailed = WebhookDelivery::factory()->create([
        'status' => WebhookDelivery::STATUS_FAILED,
        'created_at' => now()->subDays(90),
    ]);
    $recent = WebhookDelivery::factory()->succeeded()->create(['created_at' => now()->subDays(2)]);
    $pending = WebhookDelivery::factory()->create([
        'status' => WebhookDelivery::STATUS_PENDING,
        'created_at' => now()->subDays(90),
    ]);

    $this->artisan('webhooks:prune --dry-run')
        ->expectsOutputToContain('2 delivery/deliveries')
        ->assertSuccessful();

    expect(WebhookDelivery::count())->toBe(4);

    $this->artisan('webhooks:prune')->assertSuccessful();

    expect(WebhookDelivery::count())->toBe(2)
        ->and(WebhookDelivery::whereKey($recent->getKey())->exists())->toBeTrue()
        ->and(WebhookDelivery::whereKey($pending->getKey())->exists())->toBeTrue()
        ->and(WebhookDelivery::whereKey($old->getKey())->exists())->toBeFalse()
        ->and(WebhookDelivery::whereKey($oldFailed->getKey())->exists())->toBeFalse();
});

it('rejects a retention window below one day', function () {
    $this->artisan('webhooks:prune --days=0')->assertFailed();
});

it('registers both webhook commands on the scheduler', function () {
    Artisan::call('schedule:list');
    $list = Artisan::output();

    expect($list)->toContain('webhooks:retry')
        ->and($list)->toContain('webhooks:prune');
});
