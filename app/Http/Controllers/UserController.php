<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return User::with(['roles', 'trips'])->paginate(20);
    }

    public function show(User $user)
    {
        return $user->load(['roles', 'trips', 'bookings', 'complaints']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:20',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|nullable|string|min:8',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|string|max:20',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->noContent();
    }
}
