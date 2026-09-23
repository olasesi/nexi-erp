<?php

namespace App\Http\Middleware;

use App\Support\PermissionGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermitted
{
    private const ACTION_MAP = [
        'index' => 'view-any',
        'show' => 'view',
        'store' => 'create',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    /**
     * Gate a resource route on its permission (e.g. products.store needs
     * products.create). Enforcement is data-driven: a route is only gated
     * while the matching permission exists, so admins toggle checks by
     * adding/removing permissions instead of touching code.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $permission = $this->permissionFor($request->route());

        if ($permission !== null && ! $request->user()?->can($permission)) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function permissionFor(?Route $route): ?string
    {
        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        if ($name === null || ! str_contains($name, '.')) {
            return null;
        }

        [$module, $action] = explode('.', $name, 2);

        $suffix = self::ACTION_MAP[$action] ?? null;

        if ($suffix === null) {
            return null;
        }

        $permission = "{$module}.{$suffix}";

        // Guard-aware existence check: rows stored under any other guard mean
        // the permission hasn't been migrated yet, so skip enforcement.
        $exists = Permission::where('name', $permission)
            ->where('guard_name', PermissionGuard::name())
            ->exists();

        return $exists ? $permission : null;
    }
}
