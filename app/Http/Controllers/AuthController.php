<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);

        // Disallow login for disabled users.
        $data['is_active'] = 1;

        if (Auth::attempt($data, true)) {
            $request->session()->regenerate();
            return redirect()->intended(route('deals.kanban'));
        }

        return back()->withErrors(['email' => 'Неверный email или пароль'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
