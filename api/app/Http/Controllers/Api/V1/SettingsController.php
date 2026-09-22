<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateSettingGroupRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * All settings groups plus the options that power the settings UI.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->resolveCompanyId($request);

        $data = $this->settings->values($companyId);

        $meta = [
            'company_id' => $companyId,
            'currencies' => collect(config('currencies'))
                ->sortBy(fn ($c) => $c[4] ? 0 : 1)
                ->values()
                ->map(fn (array $c) => [
                    'name' => $c[1],
                    'code' => $c[2],
                    'symbol' => $c[3],
                    'is_default' => $c[4],
                ]),
            'default_currency' => collect(config('currencies'))->firstWhere(fn ($c) => $c[4])[2] ?? 'USD',
            'available_languages' => config('settings.options.languages'),
            'date_formats' => config('settings.options.dateFormats'),
            'time_formats' => config('settings.options.timeFormats'),
            'calendar_start_days' => config('settings.options.calendarStartDays'),
            'themes' => config('settings.options.themes'),
            'dashboard_widgets' => config('settings.options.dashboardWidgets'),
            'dashboard_layouts' => config('settings.options.dashboardLayouts'),
            'theme_colors' => config('settings.options.themeColors'),
            'sidebar_variants' => config('settings.options.sidebarVariants'),
            'layout_directions' => config('settings.options.layoutDirections'),
            'currency_formats' => config('settings.options.currencyFormats'),
            'currency_symbol_positions' => config('settings.options.currencySymbolPositions'),
            'storage_types' => config('settings.options.storageTypes'),
            'email_providers' => collect(config('settings.options.emailProviders'))->map(function ($name, $code) {
                return ['code' => $code, 'name' => $name];
            })->values(),
            'cache_size' => $this->settings->cacheSize(),
        ];

        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * A single settings group.
     */
    public function show(Request $request, string $group): JsonResponse
    {
        if (! config("settings.groups.{$group}")) {
            return response()->json(['message' => 'Settings group not found.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => $this->settings->group($this->resolveCompanyId($request), $group),
        ]);
    }

    /**
     * Bulk-update a settings group.
     */
    public function update(UpdateSettingGroupRequest $request, string $group): JsonResponse
    {
        if (! config("settings.groups.{$group}")) {
            return response()->json(['message' => 'Settings group not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->settings->set(
            $this->resolveCompanyId($request),
            $group,
            $request->validated()
        );

        return response()->json([
            'message' => ucwords(str_replace('_', ' ', $group)).' settings updated successfully.',
            'data' => $this->settings->group($this->resolveCompanyId($request), $group),
        ]);
    }

    /**
     * Flush application-level caches.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $this->settings->clearCache();

        return response()->json([
            'message' => 'Cache cleared successfully.',
            'cache_size' => $this->settings->cacheSize(),
        ]);
    }

    /**
     * Send a test email with the current mail transport configuration.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->settings->sendTestEmail($this->resolveCompanyId($request), $validator->validated()['email']);

            return response()->json(['message' => 'Test email sent successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Test email could not be sent: '.$e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function resolveCompanyId(Request $request): ?int
    {
        $requested = $request->input('company_id');

        if ($requested !== null && $requested !== '') {
            return (int) $requested;
        }

        return auth('api')->user()?->company_id ?? null;
    }
}
