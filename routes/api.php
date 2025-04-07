<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EventController;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Models\FcmToken;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/event', [EventController::class, 'store']);
    Route::get('/event/{event}', [EventController::class, 'show']);
    Route::delete('/event/{event}', [EventController::class, 'destroy']);
    Route::post('/event/{event}/activate', [EventController::class, 'activate']);
    Route::post('/event/{event}/finish', [EventController::class, 'finishEvent']);
    Route::post('/event/{event}/accept', [EventController::class, 'acceptParticipation']);
    Route::post('/event/{event}/decline', [EventController::class, 'declineParticipation']);

    Route::get('/users', [UserController::class, 'users']);
    Route::put('/user', [UserController::class, 'update']);
    Route::delete('/user', [UserController::class, 'destroy']);
    Route::put('/user/password', [UserController::class, 'changePassword']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth', [AuthController::class, 'authenticate']);

Route::post('/store-fcm-token', function (Request $request) {
    $request->validate([
        'token' => 'required|string|unique:fcm_tokens,token',
    ]);

    Log::info("LOGGING TOKEN: " . $request->token);

    FcmToken::updateOrCreate(
        ['user_id' => auth()->id()],
        ['token' => $request->token]
    );

    return response()->json(['message' => 'FCM Token saved successfully']);
})->middleware('auth:sanctum');
