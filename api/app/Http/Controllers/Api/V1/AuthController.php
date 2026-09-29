<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\IssuesPasswordTokens;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetToken;
use App\Services\MetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;

class AuthController extends Controller
{
    use IssuesPasswordTokens;

    public function __construct(
        protected MetricsService $metrics
    ) {}

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

        try {
            $response = $this->issuePasswordToken($credentials);
        } catch (ValidationException $e) {
            $this->metrics->recordAuth(false);

            throw $e;
        }

        $this->metrics->recordAuth(true);

        return $response;
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
     * Change the authenticated user's password after verifying the current
     * one, revoking every other active session for that account.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        if ($current = $user->token()) {
            if ($current instanceof Token) {
                $user->tokens()->where('id', '!=', $current->id)->update(['revoked' => true]);
            }
        }

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Email a password reset token, always succeeding so the endpoint cannot
     * be used to enumerate registered accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $token = Password::broker()->createToken($user);

            $user->notify(new PasswordResetToken($token, $user->email));
        }

        return response()->json(['message' => 'If that email exists, a reset token has been sent.']);
    }

    /**
     * Validate a reset token and set a new password for the account.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
            ],
            fn (User $user, string $password) => $user->forceFill(['password' => Hash::make($password)])->save()
        );

        if ($status !== Password::PASSWORD_RESET) {
            $messages = $status === Password::INVALID_USER
                ? 'This account could not be found.'
                : 'This password reset token is invalid or has expired.';

            throw ValidationException::withMessages(['email' => [$messages]]);
        }

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Returns the current authenticated user with their company and the
     * effective role permissions the frontend needs to build the sidebar.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return response()->json(
            array_merge($user->toArray(), [
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'company' => $user->company,
            ])
        );
    }
}
