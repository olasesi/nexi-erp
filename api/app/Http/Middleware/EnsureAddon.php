<?php

namespace App\Http\Middleware;

use App\Services\AddonService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group on an installed add-on being switched on for the
 * calling user's company: `Route::middleware('addon:notices')`.
 *
 * A disabled add-on answers 403 with a stable code so SPA clients can hide
 * the sidebar entry instead of showing a dead link. Routes whose add-on id
 * is absent from the catalog are never gated, so a typo fails open rather
 * than taking the API down.
 */
class EnsureAddon
{
    public function __construct(private readonly AddonService $addons) {}

    public function handle(Request $request, Closure $next, string $addon): Response
    {
        if ($this->addons->definition($addon) === null) {
            return $next($request);
        }

        if (! $this->addons->enabled($addon, $request->user()?->company_id)) {
            return response()->json([
                'message' => 'This add-on is not enabled for your company.',
                'code' => 'addon_disabled',
                'addon' => $addon,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
