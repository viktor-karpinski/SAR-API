<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth as FirebaseAuth;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FirebaseAuthMiddleware
{
    protected $firebaseAuth;

    public function __construct(FirebaseAuth $firebaseAuth)
    {
        $this->firebaseAuth = $firebaseAuth;
    }

    public function handle(Request $request, Closure $next)
    {
        $firebaseToken = $request->header('Authorization');

        if (!$firebaseToken) {
            return response()->json(['error' => 'Authorization token not found'], 401);
        }

        $cachedUser = Cache::get($firebaseToken);

        if (!$cachedUser) {
            try {
                $verifiedToken = $this->firebaseAuth->verifyIdToken($firebaseToken);
                $firebaseUid = $verifiedToken->claims()->get('sub');

                $user = User::where('firebase_uid', $firebaseUid)->first();

                if (!$user) {
                    return response()->json(['error' => 'User not found'], 404);
                }

                Cache::put($firebaseToken, $user, 600);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid or expired token', 'message' => $e->getMessage()], 401);
            }
        } else {
            $user = $cachedUser;
        }

        $request->user = $user;

        return $next($request);
    }
}
