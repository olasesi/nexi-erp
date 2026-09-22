<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

trait IssuesPasswordTokens
{
    /**
     * Exchange email/password credentials for an OAuth2 access token
     * using Passport's password grant.
     *
     * @param  array<string, string>  $credentials
     */
    protected function issuePasswordToken(array $credentials): JsonResponse
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
        } catch (LeagueOAuthServerException) {
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
