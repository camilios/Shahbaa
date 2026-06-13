<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;


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

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        return User::create($data);
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();

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

    public function update_profile(Request $request)
    {

        $users = User::find($request->id);
        if (! $request->id || ! $users) {
            return response()->json(['message' => 'User not found.'], 404);
        }
$validate = Validator::make($request->all(),
    [
        'password' => [
            'nullable',
            'string',
            Password::min(10)
                ->letters()
                ->numbers()
                ->symbols(),


                      ],
        'email' => 'nullable|email|unique:users,email|ends_with:gmail.com',
        'phone' => 'nullable|regex:/^09639[0-9]{8}$/|unique:users,phone',
    ],
    [
        'email.unique' => 'The email has already been taken.',
        'email.ends_with' => 'The email must end with @gmail.com.',
        'phone.regex' => 'The phone number format is invalid. It should start with 09639 followed by 8 digits.',
        'phone.unique' => 'The phone number has already been taken.',
    ]
);



          if($validate->fails()){
          return response()->json($validate->errors(),400);
         }

        if($request->email == $users->email || $request->email == null){
            return response()->json(['message' => 'Email already exists.' ,'email' => $users->email], 422);
        }
        else {
           $users->email = $request->email;

             if($request->phone != null){
                $users->phone = $request->phone;
            }

             if($request->password != null )
                {
                    $users->password = Crypt::encrypt($request->password);

                }


            }


       $users->save();
       return response()->json(['message' => 'Profile updated successfully.']);

    }


    public function profile(Request $request)
    {
        $user = User::find($request->id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }


        return response()->json(['name' => $user->name  ,'email' => $user->email , 'gender'=> $user->gender ,'phone'=> $user->phone]);
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

