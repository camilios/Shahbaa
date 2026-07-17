<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DriverAuthController extends Controller
{
    /**
     * Driver login.
     *
     * Drivers cannot self-register; their accounts are created by the
     * company. They authenticate with the email and password issued to
     * them and receive an API token for the mobile app.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::with('role')->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->isDriver()) {
            throw ValidationException::withMessages([
                'email' => ['This account is not authorized to sign in as a driver.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is not active. Please contact the company.'],
            ]);
        }

        $token = $user->createToken('driver_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Log the driver out by revoking the current access token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Show the authenticated driver's account information.
     */
    public function profile(Request $request)
    {
        $driver = $request->user()->load('role');

        return response()->json([
            'user' => [
                'id' => $driver->id,
                'full_name' => $driver->full_name,
                'father_name' => $driver->father_name,
                'mother_name' => $driver->mother_name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'gender' => $driver->gender,
                'national_number' => $driver->national_number,
                'status' => $driver->status,
                'photo_url' => $driver->photo_url,
                'role' => $driver->role?->name,
            ],
        ]);
    }
}
