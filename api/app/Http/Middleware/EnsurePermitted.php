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
        // Custom action routes mapped onto the standard permission suffixes.
        'summary' => 'view-any',
        'unread-count' => 'view-any',
        'profit-and-loss' => 'view',
        'balance-sheet' => 'view',
        'cash-flow' => 'view',
        'aging' => 'view',
        'sales-by-product' => 'view',
        'sales-by-category' => 'view',
        'vat-summary' => 'view',
        'import' => 'create',
        'export' => 'view',
        'post' => 'update',
        'void' => 'update',
        'complete' => 'update',
        'cancel' => 'update',
        'match' => 'update',
        'unmatch' => 'update',
        'accept' => 'update',
        'reject' => 'update',
        'receive' => 'update',
        'read-all' => 'update',
        'cache-clear' => 'update',
        'email-test' => 'update',
        'pdf' => 'view',
        'email' => 'update',
        'restore' => 'update',
        'test' => 'update',
        'rotate-secret' => 'update',
        'redeliver' => 'update',
        'deliveries' => 'view',
        'events' => 'view-any',
    ];

    /**
     * Map a route name onto the permission that should gate it, e.g.
     * products.store needs products.create. Exposed statically so coverage
     * tests can assert every routed module has a seeded permission.
     */
    public static function permissionName(?string $routeName): ?string
    {
        if ($routeName === null || ! str_contains($routeName, '.')) {
            return null;
        }

        [$module, $action] = explode('.', $routeName, 2);

        $suffix = self::ACTION_MAP[$action] ?? null;

        return $suffix === null ? null : "{$module}.{$suffix}";
    }

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

        $permission = self::permissionName($route->getName());

        if ($permission === null) {
            return null;
        }

        // Guard-aware existence check: rows stored under any other guard mean
        // the permission hasn't been migrated yet, so skip enforcement.
        $exists = Permission::where('name', $permission)
            ->where('guard_name', PermissionGuard::name())
            ->exists();

        return $exists ? $permission : null;
    }
}
