<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffAccount
{
    /**
     * Reject customers (users linked to a contact) from the staff-facing API.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isCustomer()) {
            return response()->json(['message' => 'This endpoint is not available to customer accounts.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
