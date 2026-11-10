<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

/** Номинал подарочного сертификата и сколько таких ещё не продано. */
class GiftCertificate extends Model
{
    use BelongsToAccount;

    protected $fillable = ['account_id', 'nominal', 'stock'];

    protected $casts = [
        'nominal' => 'integer',
        'stock' => 'integer',
    ];
}
