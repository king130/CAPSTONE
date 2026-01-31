<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate, create user, role profile, and return token.
        return response()->json(['message' => 'Register endpoint stub'], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        // Validate credentials, return token.
        return response()->json(['message' => 'Login endpoint stub']);
    }

    public function logout(Request $request)
    {
        // Revoke token.
        return response()->json(['message' => 'Logout endpoint stub']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
