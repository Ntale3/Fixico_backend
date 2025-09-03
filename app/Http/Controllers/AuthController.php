<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private function success($message, $data = [], $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    private function error($message, $errors = [], $status = 422)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    private function transformUser(User $user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'provider' => $user->provider,
            'is_admin' => $user->isAdmin(),
            'created_at' => $user->created_at,
            'email_verified_at' => $user->email_verified_at,
        ];
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->success('User registered successfully', [
                'user' => $this->transformUser($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 201);
        });
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            return $this->error('Invalid credentials', [], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success('Login successful', [
            'user' => $this->transformUser($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function oauthLogin(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|in:google',
            'provider_id' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'avatar' => 'nullable|url',
        ]);

        return DB::transaction(function () use ($data) {
            $user = User::where([
                'email' => $data['email'],
                'provider' => $data['provider'],
                'provider_id' => $data['provider_id'],
            ])->first();

            if (! $user) {
                $existingEmail = User::where('email', $data['email'])->first();
                if ($existingEmail) {
                    return $this->error('Email already registered with a different provider');
                }
                $data['email_verified_at'] = now();
                $user = User::create($data);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->success('OAuth login successful', [
                'user' => $this->transformUser($user),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        });
    }

    public function me(Request $request)
    {
        return $this->success('Authenticated user', [
            'user' => $this->transformUser($request->user()),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$user->id,
            'current_password' => 'sometimes|required_with:password',
            'password' => 'sometimes|string|min:8|confirmed',
            'avatar' => 'sometimes|nullable|url',
        ]);

        if (isset($data['password'])) {
            if (! $user->password || ! Hash::check($data['current_password'], $user->password)) {
                return $this->error('Current password is incorrect');
            }
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $this->success('Profile updated successfully', [
            'user' => $this->transformUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Logged out successfully');
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->success('Logged out from all devices successfully');
    }

    public function deleteAccount(Request $request)
    {
        $data = $request->validate([
            'password' => 'required_without:confirmation',
            'confirmation' => 'required_without:password|boolean',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $user = $request->user();

            if ($user->password && isset($data['password'])) {
                if (! Hash::check($data['password'], $user->password)) {
                    return $this->error('Password is incorrect');
                }
            }

            $user->tokens()->delete();
            $user->delete();

            return $this->success('Account deleted successfully');
        });
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success('Token refreshed successfully', [
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
