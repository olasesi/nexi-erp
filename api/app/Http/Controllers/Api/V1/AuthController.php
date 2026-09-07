<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

class AuthController extends Controller
{
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

    /**
     * Issue a password-grant token using the configured first-party client.
     */
    private function issuePasswordToken(array $credentials): JsonResponse
    {
        $clientId = config('services.passport.password_client_id');
        $clientSecret = config('services.passport.password_client_secret');

        if (! $clientId || ! $clientSecret) {
            throw ValidationException::withMessages([
                'email' => ['The OAuth password client is not configured.'],
            ]);
        }

        $request = (new ServerRequest('POST', '/oauth/token'))->withParsedBody([
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $credentials['email'],
            'password' => $credentials['password'],
            'scope' => '',
        ]);

        $server = app(AuthorizationServer::class);

        try {
            $response = $server->respondToAccessTokenRequest($request, new Response);
        } catch (OAuthServerException) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($response->getStatusCode() !== 200) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $payload = json_decode((string) $response->getBody(), true);

        $user = User::where('email', $credentials['email'])->firstOrFail();

        return response()->json([
            'token_type' => $payload['token_type'],
            'expires_in' => $payload['expires_in'],
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'],
            'user' => $user->load('company'),
        ]);
    }
}
