<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class NonClosureSheetRowActivity extends Model
{
    use BelongsToAccount;

    public const TYPE_STATE_UPDATED = 'state_updated';
    public const TYPE_TASK_CREATED = 'task_created';

    protected $fillable = [
        'account_id',
        'workbook_sheet_id',
        'row_state_id',
        'row_index',
        'actor_user_id',
        'type',
        'body',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function sheet()
    {
        return $this->belongsTo(NonClosureWorkbookSheet::class, 'workbook_sheet_id');
    }

    public function rowState()
    {
        return $this->belongsTo(NonClosureSheetRowState::class, 'row_state_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
