<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function drivers()
    {
        return User::query()
            ->whereRaw('LOWER(role) = ?', ['driver'])
            ->withCount('trips')
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'national_number',
                'father_name',
                'gender',
                'role',
                'status',
                'created_at',
                'updated_at',
            ])
            ->latest('id')
            ->paginate(20);
    }

    public function index()
    {
        return User::with(['roles', 'trips'])->paginate(20);
    }

    public function show(User $user)
    {
        return $user->load(['roles', 'trips', 'bookings', 'complaints']);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

        if (! empty($data['password'])) {
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

    public function block(User $user)
    {
        $user->update(['status' => 'inactive']);
        $user->tokens()->delete();

        return response()->json(['message' => 'User account blocked.', 'user' => $user]);
    }

    public function unblock(User $user)
    {
        $user->update(['status' => 'active']);

        return response()->json(['message' => 'User account activated.', 'user' => $user]);
    }
}
