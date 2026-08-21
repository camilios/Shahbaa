<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'father_name' => ['required', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:50', 'unique:users,phone'],
            'gender' => ['required', 'in:male,female'],
            'national_number' => ['required', 'string', 'max:255', 'unique:users,national_number'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('users', 'public')
            : null;

        $user = DB::transaction(function () use ($validated, $photoPath) {
            $user = User::create([
                'name' => $validated['full_name'],
                'father_name' => $validated['father_name'],
                'mother_name' => $validated['mother_name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'gender' => $validated['gender'],
                'national_number' => $validated['national_number'],
                'password' => $validated['password'],
                'photo' => $photoPath,
                'role' => 'customer',
                'status' => 'active',
            ]);

            Role::create(['name' => 'Customer', 'user_id' => $user->id]);

            return $user;
        });

        return response()->json([
            'message' => 'Registered successfully',
            'user' => $user->load('roles'),
            'token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }
    /**
     * Login
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($validated)) {

            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * User Profile
     */
    public function profile(Request $request)
    {
        return response()->json(
            $request->user()->load('role')
        );
    }
}
