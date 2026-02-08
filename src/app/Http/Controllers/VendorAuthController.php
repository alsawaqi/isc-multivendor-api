<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class VendorAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],   // email OR username (User_Id)
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = $data['login'];
        $remember = (bool)($data['remember'] ?? false);

        $credentials = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email' => $login, 'password' => $data['password']]
            : ['User_Id' => $login, 'password' => $data['password']];

        // enforce active user at login time
        $credentials['No_Login'] =  Null;

        if (!Auth::guard('vendor')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::guard('vendor')->user()->load('vendor');

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        $user = Auth::guard('vendor')->user();

        if (!$user) {
            return response()->json(['user' => null], 401);
        }

        return response()->json([
            'user' => $user->load('vendor'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('vendor')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }
}
