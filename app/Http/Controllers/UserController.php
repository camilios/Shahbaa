<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        return response()->json([
            'message' => 'User account blocked.',
            'user' => $user,
        ]);
    }

    public function unblock(User $user)
    {
        $user->update(['status' => 'active']);

        return response()->json([
            'message' => 'User account activated.',
            'user' => $user,
        ]);
    }

    public function update_profile(Request $request)
    {
        /** @var \App\Models\User|null $users */
        $users = auth('sanctum')->user();

        if (! $users) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $validate = Validator::make(
            $request->all(),
            [
                'email' => [
                    'nullable',
                    'email',
                    'ends_with:gmail.com',
                    Rule::unique('users', 'email')->ignore($users->id),
                ],
                'phone' => [
                    'nullable',
                    'regex:/^09639[0-9]{8}$/',
                    Rule::unique('users', 'phone')->ignore($users->id),
                ],
                'password' => [
                    'nullable',
                    'string',
                    \Illuminate\Validation\Rules\Password::min(10)
                        ->letters()
                        ->numbers()
                        ->symbols(),
                ],
            ],
            [
                'email.unique' => 'The email has already been taken.',
                'email.ends_with' => 'The email must end with @gmail.com.',
                'phone.regex' => 'The phone number format is invalid. It should start with 09639 followed by 8 digits.',
                'phone.unique' => 'The phone number has already been taken.',
            ]
        );

        if ($validate->fails()) {
            return response()->json($validate->errors(), 400);
        }

        if ($request->filled('email')) {
            $users->email = $request->email;
        }

        if ($request->filled('phone')) {
            $users->phone = $request->phone;
        }

        if ($request->filled('password')) {
            $users->password = Hash::make($request->password);
        }

        $users->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
        ]);
    }

    public function profile(Request $request)
    {
        $user = auth('sanctum')->user();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json([
            'full_name' => $user->full_name,
            'mother_name' => $user->mother_name,
            'email' => $user->email,
            'national_number' => $user->national_number,
            'phone' => $user->phone,
        ]);
    }

  public function Qr(Request $request)
{

    $user = User::find($request->id);



        $user->qr_token = Str::uuid();
        $user->save();



    $qr = QrCode::size(300)->generate($user->qr_token,);

        return response()->json([
            'qr' => $qr,
        ]);
    }
}
