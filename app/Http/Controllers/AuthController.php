<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\Hash;
use JWTAuth;
use Mail;

class AuthController extends Controller {

    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'registration', 'recoveryPassword']]);
    }

    public function registration(Request $request) {
        // echo "<pre>";print_r($request->all());exit;
        $rules = [
            'name' => 'required',
            'email' => 'required|unique:users',
            'phone' => 'required|unique:users',
            'role_id' => 'required',
            'profile_photo' => 'nullable',
            'position' => 'nullable',
            'password' => 'required|string|min:6',
        ];

        $message = [
            'email.required' => 'Email Or Phone field is required',
        ];

        if (!empty($request->file('profile_photo'))) {
            $rules['profile_photo'] = ['image', 'mimes:jpg,jpeg,png'];
        }

        $validator = Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $target = new User;
        $target->name = $request->name;
        $target->email = $request->email;
        $target->phone = $request->phone;
        $target->role_id = $request->role_id;

        if (!empty($request->bio_data)) {
            $target->bio_data = $request->bio_data;
        }
        if (!empty($request->skill)) {
            $target->skill = $request->skill;
        }

        $target->password = Hash::make($request->password);
        if ($files = $request->file('profile_photo')) {
            $imagePath = 'uploads/profile_photo/';
            $fileName = uniqid() . "." . date('Ymd') . "." . $files->getClientOriginalExtension();
            $dbName = $imagePath . '' . $fileName;
            $files->move(public_path($imagePath), $fileName);
            $target->profile_photo = $dbName;
        }
        if (!empty($request->address)) {
            $target->address = $request->address;
        }
        if (!empty($request->date_of_birth)) {
            $target->date_of_birth = $request->date_of_birth;
        }
        $target->position = $request->position;


        if ($target->save()) {
            return response()->json(['response' => 'success', 'message' => 'User registered','user' => $target]);
        }
    }

    public function login(Request $request) {
        $rules = [
            'email' => 'required',
            'password' => 'required|string|min:6',
        ];

        $message = [
            'email.required' => 'Email Or Phone field is required',
        ];

        $validator = Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (is_numeric($request->get('email'))) {
            $credentials = ['phone' => $request->get('email'), 'password' => $request->get('password')];
        } elseif (filter_var($request->get('email'), FILTER_VALIDATE_EMAIL)) {
            $credentials = ['email' => $request->get('email'), 'password' => $request->get('password')];
        }

        if (!$token = Auth::attempt($credentials)) {
            return response()->json([
                        'response' => 'error',
                        'message' => 'Invalid email or password',
            ]);
        }

        $user = Auth::user();
        $message = 'Successfully logged in';
        return $this->createNewToken($token, $user, $message);
    }

    public function update(Request $request) {
//        echo "<pre>";print_r($request->all());exit;
        $rules = [
            'id' => 'required|numeric',
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'role_id' => 'required',
        ];

        $message = [
            'email.required' => 'Email Or Phone field is required',
        ];

        if (!empty($request->file('profile_photo'))) {
            $rules['profile_photo'] = ['image', 'mimes:jpg,jpeg,png'];
        }
        if (!empty($request->password)) {
            $rules['password'] = ['string', 'min:6'];
        }

        $validator = Validator::make($request->all(), $rules, $message);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $target = User::findOrFail($request->id);
        $target->name = $request->name;
        $target->email = $request->email;
        $target->phone = $request->phone;
        $target->role_id = $request->role_id;
        if (!empty($request->password)) {
            $target->password = Hash::make($request->password);
        }

        if (!empty($request->bio_data)) {
            $target->bio_data = $request->bio_data;
        }
        if (!empty($request->skill)) {
            $target->skill = $request->skill;
        }
        if ($files = $request->file('profile_photo')) {
            if (file_exists(  'uploads/profile_photo/' . $target->profile_photo) && !empty($target->profile_photo)) {
                unlink('uploads/profile_photo/' . $target->profile_photo);
            }
            $imagePath = 'uploads/profile_photo/';
            $fileName = uniqid() . "." . date('Ymd') . "." . $files->getClientOriginalExtension();
            $dbName = $imagePath . '' . $fileName;
            $files->move($imagePath, $fileName);
            $target->profile_photo = $dbName;
        }

        if (!empty($request->address)) {
            $target->address = $request->address;
        }
        if (!empty($request->date_of_birth)) {
            $target->date_of_birth = $request->date_of_birth;
        }
        if (!empty($request->position)) {
            $target->position = $request->position;
        }
        if ($target->save()) {
            return response()->json(['response' => 'success', 'user' => $target]);
        }
    }

    public function logout() {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh() {
        $token = auth()->refresh();
        $user = Auth::user();
        return $this->createNewToken($token, $user);
    }

    public function updatePassword(Request $request) {
//        echo "<pre>"; print_r($request->id);exit;

        $rules = [
            'id' => 'required|numeric',
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ];


        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $target = User::findOrFail($request->id);

//        echo "<pre>";print_r($target->toArray());exit;

        if ((Hash::check($request->old_password, $target->password)) == false) {
            return response()->json(['response' => 'error', 'message' => 'Your old password does not match']);
        }
        $target->password = Hash::make($request->new_password);
        if ($target->save()) {
            return response()->json(['response' => 'success', 'message' => 'Password updated successfully', 'user' => $target]);
        }
    }

    protected function createNewToken($token, $user, $message = null) {

        return response()->json([
                    'response' => 'success',
                    'message' => $message,
                    'access_token' => $token,
                    'token_type' => 'bearer',
//                    'expires_in' => Auth::guard()->factory()->getTTL() * 60,
                    'user' => $user,
        ]);
    }

    public function recoveryPassword(Request $request) {
//        echo "<pre>";print_r($request->all());exit;

        $rules = [
            'email' => 'required|email',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $exisUser = User::where('email', $request->email)->first();
        if (!empty($exisUser)) {
            $newPass = $this->rand_string(8);
            $toEmail = $request->email;
            $toName = $exisUser->name;
            $subject = 'Your recovery password';

            $data = [
                'newPass' => $newPass,
                'toEmail' => $toEmail,
                'toName' => $toName,
                'subject' => $subject,
                'APP_NAME' => 'Attendence app',
            ];
            $exisUser->password = Hash::make($newPass);
            $exisUser->save();
            Mail::send('email-template.recover-pass', $data, function($message) use($toEmail, $toName, $subject) {
                $message->to($toEmail, $toName)->subject($subject);
            });
            return response()->json(['response' => 'success', 'message' => 'Mail sent successfully', 'to_mail' => $toEmail]);
        } else {
            return response()->json(['response' => 'error', 'message' => 'User does not exists']);
        }
    }

    public function rand_string($length) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return substr(str_shuffle($chars), 0, $length);
    }

}
