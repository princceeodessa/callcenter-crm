<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id','name','phone','email'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
