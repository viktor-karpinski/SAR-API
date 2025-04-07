<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function users()
    {
        $users = User::where('id', '!=', Auth::user()->id)
            ->where('disabled', false)
            ->get();

        return response()->json($users, 200);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:255|unique:users,phone,' . $user->id,
        ]);

        if ($request->has('name')) $user->name = $request->input('name');
        if ($request->has('email')) $user->email = $request->input('email');
        if ($request->has('phone')) $user->phone = $request->input('phone');

        $user->save();

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

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'error' => 'Current password is incorrect',
            ], 403);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }

    public function destroy()
    {
        $user = Auth::user();
        $user->disabled = true;
        $user->save();

        return response()->json([
            'message' => 'User has been disabled',
        ], 200);
    }
}
