<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $accountId = Auth::user()->account_id;

        $users = User::query()
            ->where('account_id', $accountId)
            ->orderByRaw("FIELD(role,'admin','operator')")
            ->orderBy('name')
            ->get();

        return view('settings.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $accountId = Auth::user()->account_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,operator'],
            'is_active' => ['nullable'],
        ]);

        User::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => (bool)($data['is_active'] ?? false),
        ]);

        return redirect()->route('settings.users.index')->with('status', 'Пользователь добавлен.');
    }

    public function toggleActive(User $user)
    {
        $me = Auth::user();
        abort_unless($user->account_id === $me->account_id, 403);

        // Safety: admin can't disable himself.
        if ($user->id === $me->id) {
            return back()->with('status', 'Нельзя отключить самого себя.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return back()->with('status', $user->is_active ? 'Пользователь активирован.' : 'Пользователь отключён.');
    }
}
