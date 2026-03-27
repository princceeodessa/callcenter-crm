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
            'login' => ['required', 'string', 'max:255', 'regex:/^\S+$/u'],
            'password' => ['required'],
        ]);

        $credentials = [
            'email' => strtolower(trim($data['login'])),
            'password' => $data['password'],
            'is_active' => 1,
        ];

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();
            $homeRoute = match ($request->user()?->role) {
                'measurer' => 'calendar.index',
                'constructor' => 'ceiling-projects.index',
                default => 'deals.kanban',
            };

            return redirect()->intended(route($homeRoute));
        }

        return back()->withErrors(['login' => 'Неверный логин или пароль'])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
