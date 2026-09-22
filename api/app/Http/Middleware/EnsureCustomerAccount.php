<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccount
{
    /**
     * Allow only customer accounts (users linked to a contact) into the portal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isCustomer()) {
            return response()->json(['message' => 'This endpoint is only available to customer accounts.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
