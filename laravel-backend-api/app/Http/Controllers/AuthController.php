<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TokenCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends BaseApiController
{
    /**
     * Handle user login and issue a Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            return $this->apiError(
                'Invalid credentials.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $request->session()->regenerate();

        $user = $request->user();

        return $this->apiSuccess([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        \Log::info('Logout called', [
            'user_id' => $request->user()?->id,
            'session_id' => $request->session()->getId(),
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        \Log::info('After logout', [
            'auth_check' => Auth::check(),
            'session_id' => $request->session()->getId(),
        ]);

        // Clear the session cookie
        $response = $this->apiSuccess([
            'logout' => true,
        ]);

        // Force the session cookie to be cleared
        return $response->withCookie(cookie()->forget(config('session.cookie')));
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request)
    {
        \Log::info('/auth/me called', [
            'auth_check' => Auth::check(),
            'user_id' => $request->user()?->id,
            'session_id' => $request->session()->getId(),
            'has_session_cookie' => $request->hasCookie(config('session.cookie')),
        ]);

        $user = $request->user();

        if (!$user) {
            return $this->apiError('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->apiSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * Return the authenticated user by Bearer token only (no session fallback).
     */
    public function meToken(Request $request)
    {
        $auth = (string) $request->header('Authorization');
        if (! preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return $this->apiError('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $plain = trim($m[1] ?? '');
        if ($plain === '') {
            return $this->apiError('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenModel = PersonalAccessToken::findToken($plain);
        if (! $tokenModel) {
            return $this->apiError('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        $user = $tokenModel->tokenable;

        return $this->apiSuccess([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * Create a personal access token using email/password (token-based auth).
     */
    public function tokenCreate(TokenCreateRequest $request)
    {
        $data = $request->validated();

        $user = \App\Models\User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->apiError('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'api-client';
        $token = $user->createToken($deviceName, ['*']);

        return $this->apiSuccess([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Revoke the current personal access token.
     */
    public function tokenRevoke(Request $request)
    {
        // 1) Delete the currently authenticated token when available
        $current = $request->user()?->currentAccessToken();
        if ($current) {
            $current->delete();
        }

        // 2) Also delete by locating the token via the plain text value from Authorization header
        $auth = (string) $request->header('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            $plain = trim($m[1]);
            // findToken hashes and compares safely
            if ($plain !== '') {
                if ($tokenModel = PersonalAccessToken::findToken($plain)) {
                    $tokenModel->delete();
                }
            }
        }

        return $this->apiSuccess([
            'revoked' => true,
        ]);
    }
}
