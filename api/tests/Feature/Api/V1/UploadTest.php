<?php

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

function grantUploadPermission(User $user): void
{
    Permission::firstOrCreate([
        'name' => 'uploads.create',
        'guard_name' => PermissionGuard::name(),
    ]);

    $user->givePermissionTo('uploads.create');
}

it('stores an uploaded file and returns its metadata', function () {
    grantUploadPermission($this->user);

    $file = UploadedFile::fake()->create('report.pdf', 120, 'application/pdf');

    $response = $this->postJson('/api/v1/uploads', ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.name', 'report.pdf')
        ->assertJsonPath('data.mime', 'application/pdf');

    $path = $response->json('data.path');

    expect($path)->toStartWith('uploads/'.now()->format('Y/m').'/');
    Storage::disk('public')->assertExists($path);
});

it('rejects disallowed file types', function () {
    grantUploadPermission($this->user);

    $file = UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream');

    $this->postJson('/api/v1/uploads', ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('requires the upload permission', function () {
    Permission::firstOrCreate([
        'name' => 'uploads.create',
        'guard_name' => PermissionGuard::name(),
    ]);

    $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

    $this->postJson('/api/v1/uploads', ['file' => $file])->assertForbidden();

    Storage::disk('public')->assertDirectoryEmpty('uploads');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $file = UploadedFile::fake()->create('report.pdf', 10, 'application/pdf');

    $this->postJson('/api/v1/uploads', ['file' => $file])->assertUnauthorized();
});
