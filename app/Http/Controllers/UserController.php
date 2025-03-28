<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function users()
    {
        $users = User::where('id', '!=', Auth::user()->id)->get();

        return response()->json($users, 200);
    }
}
