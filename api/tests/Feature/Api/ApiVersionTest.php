<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

function deprecateV1(string $replacement = 'v2', ?string $sunset = '2027-06-30'): void
{
    config([
        'api.versions.v1.status' => 'deprecated',
        'api.versions.v1.replaced_by' => $replacement,
        'api.versions.v1.sunset_at' => $sunset,
        'api.versions.v1.notice' => 'Version 1 closes on 2027-06-30, move to v2.',
    ]);

    config(['api.versions.v2' => [
        'status' => 'stable',
        'sunset_at' => null,
        'replaced_by' => null,
        'notice' => null,
    ]]);
}

it('stamps the served version on every API response', function () {
    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertHeader('X-Api-Version', 'v1');

    $this->getJson('/api/health')
        ->assertOk()
        ->assertHeader('X-Api-Version', 'v1');
});

it('advertises the published versions on the discovery endpoint', function () {
    deprecateV1();

    $response = $this->getJson('/api/versions')->assertOk();

    expect($response->json('data.default'))->toBe('v1')
        ->and($response->json('data.served'))->toBe('v1')
        ->and(array_column($response->json('data.versions'), 'version'))->toBe(['v1', 'v2'])
        ->and($response->json('data.versions.0.status'))->toBe('deprecated')
        ->and($response->json('data.versions.0.media_type'))->toBe('application/vnd.nexi.v1+json')
        ->and($response->json('data.versions.0.replaced_by'))->toBe('v2')
        ->and($response->json('data.versions.1.status'))->toBe('stable');
});

it('refuses a version that does not exist', function () {
    Route::middleware('api')->prefix('api')->get('/v9/ping', fn (): JsonResponse => response()->json(['ok' => true]));

    $this->getJson('/api/v9/ping')
        ->assertNotFound()
        ->assertJsonPath('message', 'API version "v9" does not exist.')
        ->assertJsonPath('requested_version', 'v9')
        ->assertJsonPath('supported_versions', ['v1']);

    // An unversioned path simply does not exist.
    $this->getJson('/api/v9/user')->assertNotFound();
});

it('refuses a version asked for through the Accept media type', function () {
    $this->getJson('/api/health', ['Accept' => 'application/vnd.nexi.v4+json'])
        ->assertStatus(400)
        ->assertJsonPath('requested_version', 'v4');
});

it('serves the version negotiated from the Accept media type', function () {
    deprecateV1();

    $this->getJson('/api/health', ['Accept' => 'application/vnd.nexi.v2+json'])
        ->assertOk()
        ->assertHeader('X-Api-Version', 'v2')
        ->assertHeaderMissing('Deprecation');
});

it('lets the path version win over the Accept media type and the header', function () {
    deprecateV1();

    $this->getJson('/api/v1/user', [
        'Accept' => 'application/vnd.nexi.v2+json',
        'X-Api-Version' => 'v2',
    ])
        ->assertOk()
        ->assertHeader('X-Api-Version', 'v1');
});

it('serves the version asked for through the version header', function () {
    deprecateV1();

    $this->getJson('/api/health', ['X-Api-Version' => 'v2'])
        ->assertOk()
        ->assertHeader('X-Api-Version', 'v2');
});

it('advertises deprecation, sunset and the replacement on a deprecated version', function () {
    deprecateV1();

    $response = $this->getJson('/api/v1/user')->assertOk();

    $response->assertHeader('Deprecation', 'true')
        ->assertHeader('Sunset', 'Wed, 30 Jun 2027 00:00:00 GMT')
        ->assertHeader('Link', '</api/v2/user>; rel="deprecation"; type="application/vnd.nexi.v2+json"')
        ->assertHeader('Warning', '299 - "Version 1 closes on 2027-06-30, move to v2."');
});

it('answers a sunset version with 410 gone', function () {
    config([
        'api.versions.v1.status' => 'stable',
        'api.versions.v1.sunset_at' => now()->subDay()->toDateString(),
    ]);

    $this->getJson('/api/v1/user')
        ->assertStatus(410)
        ->assertJsonPath('requested_version', 'v1')
        ->assertJsonPath('supported_versions', ['v1']);
});

it('keeps a deprecated version without a sunset date working', function () {
    config([
        'api.versions.v1.status' => 'deprecated',
        'api.versions.v1.sunset_at' => null,
        'api.versions.v1.replaced_by' => null,
    ]);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertHeader('Deprecation', 'true')
        ->assertHeaderMissing('Sunset')
        ->assertHeaderMissing('Link')
        ->assertHeader('Warning', '299 - "API version v1 is deprecated."');
});
