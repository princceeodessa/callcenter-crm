<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NonClosureWorkspace extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'title',
        'document_html',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $attributes = [
        'title' => 'Документы и таблицы',
    ];

    public function workbooks()
    {
        return $this->hasMany(NonClosureWorkbook::class, 'workspace_id')
            ->orderByDesc('imported_at')
            ->orderByDesc('id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
