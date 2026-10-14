<?php

use App\Models\AiAgentThread;
use App\Models\AiDocument;
use App\Models\AiImage;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessSettingsService;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    config([
        'ai.driver' => 'openai',
        'ai.document_driver' => 'openai',
        'ai.image_driver' => 'openai',
        'ai.providers.openai.api_key' => 'sk-test',
        'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
    ]);
});

function grantAiOpenAiPermissions(User $user, array $modules): void
{
    foreach ($modules as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $prefixes = array_map(fn ($module) => $module.'.', $modules);

    $user->givePermissionTo(
        Permission::query()
            ->where(function ($query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('name', 'like', $prefix.'%');
                }
            })
            ->get()
    );
}

it('answers an agent thread through the OpenAI driver', function () {
    grantAiOpenAiPermissions($this->user, ['ai-agent-threads', 'ai-agent-messages', 'ai-agent-prompts']);

    $thread = AiAgentThread::factory()->create(['company_id' => $this->company->id]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello from GPT.']]],
            'usage' => ['total_tokens' => 21],
        ]),
    ]);

    $this->postJson("/api/v1/ai-agent-threads/{$thread->id}/messages", ['body' => 'Summarise Q3.'])
        ->assertCreated()
        ->assertJsonPath('data.1.role', 'assistant')
        ->assertJsonPath('data.1.body', 'Hello from GPT.');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['messages'][0]['role'] === 'user'
        && $request['messages'][0]['content'] === 'Summarise Q3.');
});

it('drafts a document through the OpenAI driver', function () {
    grantAiOpenAiPermissions($this->user, ['ai-documents']);

    $document = AiDocument::factory()->create([
        'company_id' => $this->company->id,
        'prompt' => 'Write a refund policy.',
        'title' => 'Refund policy',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Refunds within 30 days.']]],
            'usage' => ['total_tokens' => 42],
        ]),
    ]);

    $this->postJson("/api/v1/ai-documents/{$document->id}/generate")
        ->assertOk()
        ->assertJsonPath('data.status', AiDocument::STATUS_GENERATED)
        ->assertJsonPath('data.provider', 'openai')
        ->assertJsonPath('data.content', 'Refunds within 30 days.')
        ->assertJsonPath('data.tokens', 42);
});

it('renders an image through the OpenAI driver', function () {
    grantAiOpenAiPermissions($this->user, ['ai-images']);

    $image = AiImage::factory()->create([
        'company_id' => $this->company->id,
        'prompt' => 'A minimalist fintech logo',
        'size' => '1024x1024',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [['url' => 'https://images.example.com/logo.png']],
        ]),
    ]);

    $this->postJson("/api/v1/ai-images/{$image->id}/generate")
        ->assertOk()
        ->assertJsonPath('data.status', AiImage::STATUS_GENERATED)
        ->assertJsonPath('data.provider', 'openai')
        ->assertJsonPath('data.url', 'https://images.example.com/logo.png');
});

it('uses a company stored key when one is configured', function () {
    grantAiOpenAiPermissions($this->user, ['ai-documents']);

    app(BusinessSettingsService::class)->set($this->company->id, 'ai_document', [
        'api_key' => 'sk-company',
        'model' => 'gpt-4o',
    ]);

    $document = AiDocument::factory()->create(['company_id' => $this->company->id]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'ok']]],
        ]),
    ]);

    $this->postJson("/api/v1/ai-documents/{$document->id}/generate")->assertOk();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-company')
        && $request['model'] === 'gpt-4o');
});
