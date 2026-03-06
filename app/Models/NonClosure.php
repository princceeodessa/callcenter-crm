<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NonClosure extends Model
{
    use HasFactory, BelongsToAccount;

    public const RESULT_STATUSES = [
        '' => 'Без статуса',
        'concluded' => 'Заключен',
        'not_concluded' => 'Не заключен',
    ];

    protected $fillable = [
        'account_id',
        'entry_date',
        'address',
        'reason',
        'measurer_user_id',
        'measurer_name',
        'responsible_user_id',
        'responsible_name',
        'comment',
        'follow_up_date',
        'result_status',
        'special_calculation',
        'created_by_user_id',
        'updated_by_user_id',
        'source',
        'unique_hash',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'follow_up_date' => 'date',
    ];

    public function measurerUser()
    {
        return $this->belongsTo(User::class, 'measurer_user_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function getDisplayMeasurerAttribute(): ?string
    {
        return $this->measurerUser?->name ?: ($this->measurer_name ?: null);
    }

    public function getDisplayResponsibleAttribute(): ?string
    {
        return $this->responsibleUser?->name ?: ($this->responsible_name ?: null);
    }
}
