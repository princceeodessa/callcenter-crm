<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CeilingProjectRoom extends Model
{
    use BelongsToAccount;

    public const SHAPE_RECTANGLE = 'rectangle';
    public const SHAPE_POLYGON = 'polygon';

    protected $fillable = [
        'account_id',
        'ceiling_project_id',
        'sort_order',
        'name',
        'shape_type',
        'width_m',
        'length_m',
        'height_m',
        'corners_count',
        'manual_area_m2',
        'manual_perimeter_m',
        'shape_points',
        'spotlights_count',
        'chandelier_points_count',
        'pipes_count',
        'curtain_niches_count',
        'ventilation_holes_count',
        'notes',
    ];

    protected $casts = [
        'width_m' => 'decimal:2',
        'length_m' => 'decimal:2',
        'height_m' => 'decimal:2',
        'manual_area_m2' => 'decimal:2',
        'manual_perimeter_m' => 'decimal:2',
        'shape_points' => 'array',
    ];

    public static function shapeOptions(): array
    {
        return [
            self::SHAPE_RECTANGLE => 'Прямоугольник',
            self::SHAPE_POLYGON => 'Сложная форма',
        ];
    }

    public function project()
    {
        return $this->belongsTo(CeilingProject::class, 'ceiling_project_id');
    }

    public function elements()
    {
        return $this->hasMany(CeilingProjectRoomElement::class)->orderBy('sort_order')->orderBy('id');
    }
}
