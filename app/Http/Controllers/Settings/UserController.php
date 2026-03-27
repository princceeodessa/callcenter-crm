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
            ->orderByRaw("FIELD(role,'admin','main_operator','operator','measurer','constructor')")
            ->orderBy('name')
            ->get();

        return view('settings.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $accountId = Auth::user()->account_id;
        $usesSplitName = $request->filled('first_name') || $request->filled('last_name');

        $data = $request->validate([
            'first_name' => [$usesSplitName ? 'required' : 'nullable', 'string', 'max:255'],
            'last_name' => [$usesSplitName ? 'required' : 'nullable', 'string', 'max:255'],
            'name' => [$usesSplitName ? 'nullable' : 'required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', 'regex:/^\S+$/u', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,main_operator,operator,measurer,constructor'],
            'is_active' => ['nullable'],
        ]);

        $name = $usesSplitName
            ? preg_replace('/\s+/u', ' ', trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')))
            : preg_replace('/\s+/u', ' ', trim($data['name']));

        User::create([
            'account_id' => $accountId,
            'name' => $name,
            'email' => strtolower(trim($data['login'])),
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? false),
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

        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('status', $user->is_active ? 'Пользователь активирован.' : 'Пользователь отключён.');
    }
}
