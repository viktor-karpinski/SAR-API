<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Auth;
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

        $phone = $this->checkPhone($request->phone);

        if ($phone['valid']) {
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
        } else {
            return response()->json([
                'errors' => ['phone' => 'Neplatné telefónne číslo'],
            ], 422);
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
}
