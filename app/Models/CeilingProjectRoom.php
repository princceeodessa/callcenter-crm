<?php


namespace App\Models;


use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;


class CeilingProjectRoom extends Model
{
    use BelongsToAccount;


    public const SHAPE_RECTANGLE = 'rectangle';
    public const SHAPE_POLYGON = 'polygon';


    public const FEATURE_CUTOUT = 'cutout';
    public const FEATURE_LEVEL = 'level';
    public const FEATURE_SHIFT = 'shift';


    public const FEATURE_RECTANGLE = 'rectangle';
    public const FEATURE_CIRCLE = 'circle';
    public const FEATURE_TRIANGLE = 'triangle';
    public const FEATURE_POLYGON = 'polygon';
    public const FEATURE_ARC = 'arc';
    public const FEATURE_ROUNDED_CORNER = 'rounded_corner';


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
        'feature_shapes',
        'light_line_shapes',
        'derived_panels',
        'production_settings',
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
        'feature_shapes' => 'array',
        'light_line_shapes' => 'array',
        'derived_panels' => 'array',
        'production_settings' => 'array',
    ];


    public static function shapeOptions(): array
    {
        return [
            self::SHAPE_RECTANGLE => 'Прямоугольник',
            self::SHAPE_POLYGON => 'Сложная форма',
        ];


        return [
            self::SHAPE_RECTANGLE => 'Прямоугольник',
            self::SHAPE_POLYGON => 'Сложная форма',
        ];
    }


    public static function featureKindOptions(): array
    {
        return [
            self::FEATURE_CUTOUT => 'Вырез',
            self::FEATURE_LEVEL => 'Уровень',
            self::FEATURE_SHIFT => 'Сдвиг',
        ];


        return [
            self::FEATURE_CUTOUT => 'Вырез',
            self::FEATURE_LEVEL => 'Уровень',
            self::FEATURE_SHIFT => 'Сдвиг',
        ];
    }


    public static function featureFigureOptions(): array
    {
        return [
            self::FEATURE_RECTANGLE => 'Прямоугольник',
            self::FEATURE_CIRCLE => 'Круг',
            self::FEATURE_TRIANGLE => 'Треугольник',
            self::FEATURE_POLYGON => 'Многоугольник',
            self::FEATURE_ARC => 'Дуга',
            self::FEATURE_ROUNDED_CORNER => 'Скругление угла',
        ];


        return [
            self::FEATURE_RECTANGLE => 'Прямоугольник',
            self::FEATURE_CIRCLE => 'Круг',
            self::FEATURE_TRIANGLE => 'Треугольник',
            self::FEATURE_POLYGON => 'Многоугольник',
            self::FEATURE_ARC => 'Дуга',
            self::FEATURE_ROUNDED_CORNER => 'Скругление угла',
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
