<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CeilingProjectRoomElement extends Model
{
    use BelongsToAccount;

    public const TYPE_SPOTLIGHT = 'spotlight';
    public const TYPE_CHANDELIER = 'chandelier';
    public const TYPE_PIPE = 'pipe';
    public const TYPE_CURTAIN_NICHE = 'curtain_niche';
    public const TYPE_VENTILATION = 'ventilation';
    public const TYPE_CORNICE = 'cornice';
    public const TYPE_CUSTOM = 'custom';
    public const PLACEMENT_FREE = 'free';
    public const PLACEMENT_WALL = 'wall';

    protected $fillable = [
        'account_id',
        'ceiling_project_room_id',
        'sort_order',
        'type',
        'label',
        'quantity',
        'placement_mode',
        'segment_index',
        'offset_m',
        'x_m',
        'y_m',
        'length_m',
        'notes',
    ];

    protected $casts = [
        'x_m' => 'decimal:2',
        'y_m' => 'decimal:2',
        'offset_m' => 'decimal:2',
        'length_m' => 'decimal:2',
        'quantity' => 'integer',
        'segment_index' => 'integer',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_SPOTLIGHT => 'Спот',
            self::TYPE_CHANDELIER => 'Люстра',
            self::TYPE_PIPE => 'Труба',
            self::TYPE_CURTAIN_NICHE => 'Ниша',
            self::TYPE_VENTILATION => 'Вентиляция',
            self::TYPE_CORNICE => 'Карниз',
            self::TYPE_CUSTOM => 'Другое',
        ];
    }

    public static function placementOptions(): array
    {
        return [
            self::PLACEMENT_FREE => 'Свободно',
            self::PLACEMENT_WALL => 'По стене',
        ];
    }

    public function room()
    {
        return $this->belongsTo(CeilingProjectRoom::class, 'ceiling_project_room_id');
    }
}
