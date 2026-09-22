<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\IssuesPasswordTokens;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use IssuesPasswordTokens;

    /**
     * Exchange email/password credentials for an OAuth2 access token
     * using Passport's password grant.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return $this->issuePasswordToken($credentials);
    }

    /**
     * Register a new user and immediately issue an OAuth2 access token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->issuePasswordToken([
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($token = $request->user()->token()) {
            $token->revoke();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Returns the current authenticated user with their company.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('company'));
    }
}
