<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseApiController
{
    /**
     * Handle user login and issue a Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->apiError(
                'Invalid credentials.',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Optionally clear existing tokens to enforce single-session behaviour.
        $user->tokens()->delete();

        $token = $user->createToken('api')->plainTextToken;

        return $this->apiSuccess([
            'token' => $token,
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
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->apiSuccess([
            'logout' => true,
        ]);
    }
}
