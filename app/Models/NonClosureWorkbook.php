<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NonClosureWorkbook extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id',
        'workspace_id',
        'title',
        'source_name',
        'source_hash',
        'uploaded_by_user_id',
        'owner_user_id',
        'summary',
        'imported_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'imported_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(NonClosureWorkspace::class, 'workspace_id');
    }

    public function sheets()
    {
        return $this->hasMany(NonClosureWorkbookSheet::class, 'workbook_id')->orderBy('position')->orderBy('id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
