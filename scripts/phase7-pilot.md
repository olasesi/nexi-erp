# Phase 7 — AI Document & AI Images — Pilot Runbook

Adds two AI add-ons with provider-agnostic drivers and an offline stub by
default. Each module is gated by `addon:<name>` and the corresponding
`<module>.*` permissions.

| Add-on | Modules | Routes | Permission model |
|---|---|---|---|
| `ai-document` | `ai-documents` | `GET/POST /api/v1/ai-documents`, `GET/PUT/PATCH/DELETE /api/v1/ai-documents/{id}`, `POST /api/v1/ai-documents/{id}/generate`, `POST /api/v1/ai-documents/{id}/restore` | `ai-documents.view-any|view|create|update|delete` (`generate` maps to `create`) |
| `ai-image` | `ai-images` | `GET/POST /api/v1/ai-images`, `GET/PUT/PATCH/DELETE /api/v1/ai-images/{id}`, `POST /api/v1/ai-images/{id}/generate`, `POST /api/v1/ai-images/{id}/restore` | `ai-images.view-any|view|create|update|delete` (`generate` maps to `create`) |

## 1. Contracts & drivers

- `App\Contracts\AiDocumentDriver` — `generate(string $prompt, array $options = []): array{content:string,provider:string,model:string|null,tokens:int|null}`
- `App\Contracts\AiImageDriver` — `generate(string $prompt, array $options = []): array{url:string,provider:string,model:string|null}`
- Stub: `App\Ai\Drivers\StubAiDocumentDriver`, `StubAiImageDriver` (deterministic output; image yields a data URI SVG).
- Config: `config/ai.php` adds `document_driver`/`document_drivers` and `image_driver`/`image_drivers`; defaults to `stub`.
- Services: `AiDocumentService`, `AiImageService` resolve the active driver (fall back to stub).

## 2. Models, storage & resources

- Migrations: `ai_documents` (company_id,user_id,title,prompt,content,template,language,provider,model,tokens,status,generated_at,meta,timestamps,softDeletes; index [company_id,status]), `ai_images` (company_id,user_id,prompt,negative_prompt,style,size,provider,model,url,status,generated_at,meta,timestamps,softDeletes; index [company_id,status]).
- Models: `AiDocument`, `AiImage` — `CompanyScoped`, `DispatchesWebhooks`, `RecordsActivity`, `SoftDeletes`. Constants `STATUS_PENDING`, `STATUS_GENERATED`.
- Resources: `AiDocumentResource`, `AiImageResource` include `generated_at` (toDateTimeString), `meta`, user when loaded.

## 3. Flow

- Create a draft: `POST /ai-documents` (or `ai-images`) with required `prompt` (document optional `title/template/language`, image optional `negative_prompt/style/size`, default `size 512x512`).
- Generate: `POST /{id}/generate` invokes the configured driver; `status` becomes `generated`, fields (`content`, `provider`, `model`, `tokens`, `generated_at`) / (`url`, `provider`, `model`, `generated_at`) are populated.

## 4. Local verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes config/ai.php config/addons.php
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=AiDocumentTest
php artisan test --filter=AiImageTest
php artisan test --filter=PermissionCoverageTest
php artisan test  # full suite (run detached in CI-like context)
```

## 5. Smoke tests (API)

```powershell
$t=(Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json -Body (@{email="admin@nexi-corp.com";password="password"}|ConvertTo-Json)).access_token
$h=@{Authorization="Bearer $t"}

# create doc
$d=(Invoke-RestMethod http://localhost:8000/api/v1/ai-documents -Method Post -Headers $h -ContentType application/json -Body (@{title="Policy";prompt="Write a short refund policy"}|ConvertTo-Json)).data; $d.id

# generate
(Invoke-RestMethod http://localhost:8000/api/v1/ai-documents/$($d.id)/generate -Method Post -Headers $h).data | ConvertTo-Json -Compress

# create image + generate
$img=(Invoke-RestMethod http://localhost:8000/api/v1/ai-images -Method Post -Headers $h -ContentType application/json -Body (@{prompt="A small blue square"}|ConvertTo-Json)).data
(Invoke-RestMethod http://localhost:8000/api/v1/ai-images/$($img.id)/generate -Method Post -Headers $h).data.url  # starts data:image/svg+xml;base64,...
```

## 6. Company isolation & addon gating

- All queries are company-scoped (global scope); list endpoints only return the acting user's company records.
- Add-on disabled (`/addons/ai-document` → enabled:false) blocks all `ai-documents*` routes with `403 {code:'addon_disabled', addon:'ai-document'}`.
- RolesAndPermissionsSeeder seeds `<module>.*` actions from `config/addons.php` automatically.
