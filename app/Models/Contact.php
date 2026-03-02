<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'account_id','name','phone','email'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
