<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'hide_in_reports' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Чьи страницы «SMM-контент» видит пользователь: ceilings (БлагоДар) и/или sneakers («Шаговый»).
     * Владелец всех бизнесов — оба; владелец и руководитель кроссовок — кроссовки. SMM-специалист (роль smm) видит
     * только эти страницы: с all_businesses — оба сообщества, иначе — сообщество своего пространства.
     *
     * @return list<string>
     */
    public function smmBusinesses(): array
    {
        return match ($this->role) {
            'sneaker_owner' => $this->all_businesses ? ['ceilings', 'sneakers'] : ['sneakers'],
            'sneaker_head' => ['sneakers'],
            'smm' => $this->all_businesses ? ['ceilings', 'sneakers']
                : [(int) $this->account_id === (int) config('owner.ceilings_account_id', 1) ? 'ceilings' : 'sneakers'],
            default => [],
        };
    }
}
