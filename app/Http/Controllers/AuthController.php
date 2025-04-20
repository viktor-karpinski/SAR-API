<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    public function register(Request $request)
    {
        $phone = $this->checkPhone($request->phone);

        if (!$phone['valid']) {
            return response()->json([
                'errors' => ['phone' => 'Neplatné telefónne číslo'],
            ], 422);
        }

        $request->merge(['phone' => $phone['formatted']]);

        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:users,phone',
        ], [
            'email.required' => 'Pole e-mail je povinné',
            'email.email' => 'Zadajte platnú e-mailovú adresu',
            'email.unique' => 'Zadaný e-mail už existuje',

            'password.required' => 'Pole heslo je povinné',
            'password.string' => 'Heslo musí byť textový reťazec',
            'password.min' => 'Heslo musí mať aspoň 6 znakov',

            'name.required' => 'Pole meno je povinné',
            'name.string' => 'Meno musí byť textový reťazec',
            'name.max' => 'Meno môže mať maximálne 255 znakov',

            'phone.required' => 'Pole telefónne číslo je povinné',
            'phone.string' => 'Telefónne číslo musí byť textový reťazec',
            'phone.max' => 'Telefónne číslo môže mať maximálne 255 znakov',
            'phone.unique' => 'Zadané telefónne číslo už existuje',
        ]);

        try {
            $userProperties = [
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'displayName' => $request->input('name'),
            ];

            $firebaseUser = $this->firebaseAuth->createUser($userProperties);

            $user = User::create([
                'firebase_uid' => $firebaseUser->uid,
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $phone['formatted'],
                'disabled' => true,
            ]);

            $signInResult = $this->firebaseAuth->signInWithEmailAndPassword(
                $request->input('email'),
                $request->input('password')
            );

            $idToken = $signInResult->idToken();

            return response()->json([
                'user' => $user,
                'message' => 'User registered successfully',
                'firebase_token' => $idToken,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to register user',
                'message' => $e->getMessage(),
            ], 400);
        }


        return response()->json([
            'error' => 'Something went wrong',
        ], 500);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'firebase_token' => 'required|string',
        ]);

        try {
            $verifiedToken = $this->firebaseAuth->verifyIdToken($request->input('firebase_token'));
            $firebaseUid = $verifiedToken->claims()->get('sub');

            $user = User::firstOrCreate(
                ['firebase_uid' => $firebaseUid],
                [
                    'name' => $verifiedToken->claims()->get('name', 'Unknown'),
                    'email' => $verifiedToken->claims()->get('email', null),
                ]
            );

            if (!$user->disabled) {
                Auth::login($user);
                $user->refresh();

                $token = $user->createToken('firebase-token')->plainTextToken;

                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Authenticated successfully',
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Invalid Firebase token',
                'message' => $e->getMessage(),
            ], 401);
        }

        return response()->json([
            'error' => 'Invalid Login',
            'message' => 'Invalid Login',
        ], 401);
    }

    private function checkPhone($phone)
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        if (strlen($phone) > 3) {
            $country = "SK";
            if (substr($phone, 0, 3) === '+43') {
                $country = 'AT';
            } elseif (substr($phone, 0, 3) === '+48') {
                $country = 'PL';
            } elseif (substr($phone, 0, 4) === '+421') {
                $country = 'SK';
            } elseif (substr($phone, 0, 4) === '+420') {
                $country = 'CZ';
            }

            try {
                $numberProto = $phoneUtil->parse($phone, $country);
                $isValid = $phoneUtil->isValidNumber($numberProto);
                if ($isValid) {
                    $formatted = $phoneUtil->format($numberProto, PhoneNumberFormat::INTERNATIONAL);
                    return ['valid' => true, 'formatted' => $formatted];
                } else {
                    return ['valid' => false];
                }
            } catch (\libphonenumber\NumberParseException $e) {
                return ['valid' => false];
            }
        }
        return ['valid' => false];
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        if ($request->phone !== $user->phone) {
            $phone = $this->checkPhone($request->phone);

            if (!$phone['valid']) {
                return response()->json([
                    'errors' => ['phone' => 'Neplatné telefónne číslo'],
                ], 422);
            }

            $request->merge(['phone' => $phone['formatted']]);

            $request->validate([
                'phone' => 'required|string|max:255|unique:users,phone',
            ], [
                'phone.required' => 'Pole telefónne číslo je povinné',
                'phone.string' => 'Telefónne číslo musí byť textový reťazec',
                'phone.max' => 'Telefónne číslo môže mať maximálne 255 znakov',
                'phone.unique' => 'Zadané telefónne číslo už existuje',
            ]);

            $user->phone = $request->phone;
            $user->save();
        }

        $savedInFirebase = true;

        try {
            $updateData = [];

            if ($request->has('email') && $request->email !== $user->email) {
                $request->validate([
                    'email' => 'required|email|unique:users,email',
                ], [
                    'email.required' => 'Pole e-mail je povinné',
                    'email.email' => 'Zadajte platnú e-mailovú adresu',
                    'email.unique' => 'Zadaný e-mail už existuje',
                ]);
                $user->email = $request->email;
                $updateData['email'] = $request->input('email');
            }

            if ($request->has('name') && $request->name !== $user->name) {
                $request->validate([
                    'name' => 'required|string|max:255',
                ], [
                    'name.required' => 'Pole meno je povinné',
                    'name.string' => 'Meno musí byť textový reťazec',
                    'name.max' => 'Meno môže mať maximálne 255 znakov',
                ]);
                $user->name = $request->name;
                $updateData['displayName'] = $request->input('name');
            }

            if (!empty($updateData)) {
                $this->firebaseAuth->updateUser($user->firebase_uid, $updateData);
            }
        } catch (\Throwable $e) {
            $savedInFirebase = false;
        }

        if ($savedInFirebase) {
            $user->save();
        } else {
            return response()->json([
                'errors' => [
                    'email' => 'ERROR'
                ],
            ], 500);
        }

        return response()->json([
            'message' => 'User profile updated successfully',
            'user' => $user,
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        try {
            $this->firebaseAuth->signInWithEmailAndPassword(
                $user->email,
                $request->input('current_password')
            );
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => ['current_password' => 'Aktuálne heslo je nesprávne'],
            ], 403);
        }

        try {
            $this->firebaseAuth->changeUserPassword($user->firebase_uid, $request->input('new_password'));

            return response()->json([
                'message' => 'Password changed successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => ['current_password' => 'Niečo sa pokazilo, skúste to znova neskôr'],
            ], 500);
        }
    }


    public function destroy()
    {
        $user = Auth::user();

        try {
            $this->firebaseAuth->deleteUser($user->firebase_uid);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to delete user from Firebase',
                'message' => $e->getMessage(),
            ], 500);
        }

        $user->disabled = true;
        $user->save();

        return response()->json([
            'message' => 'User has been deleted from Firebase and disabled locally',
        ], 200);
    }
}
