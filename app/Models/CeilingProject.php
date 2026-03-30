<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class CeilingProject extends Model
{
    use BelongsToAccount;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_READY = 'ready';

    protected $fillable = [
        'account_id',
        'deal_id',
        'measurement_id',
        'created_by_user_id',
        'updated_by_user_id',
        'title',
        'status',
        'calculator_version',
        'canvas_material',
        'canvas_texture',
        'canvas_color',
        'mounting_system',
        'waste_percent',
        'extra_margin_m',
        'discount_percent',
        'canvas_price_per_m2',
        'profile_price_per_m',
        'insert_price_per_m',
        'spotlight_price',
        'chandelier_price',
        'pipe_price',
        'curtain_niche_price',
        'cornice_price_per_m',
        'ventilation_hole_price',
        'mounting_price_per_m2',
        'additional_cost',
        'reference_image_path',
        'sketch_image_path',
        'sketch_crop',
        'sketch_recognition',
        'sketch_recognized_at',
        'notes',
        'last_calculated_at',
    ];

    protected $casts = [
        'waste_percent' => 'decimal:2',
        'extra_margin_m' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'canvas_price_per_m2' => 'decimal:2',
        'profile_price_per_m' => 'decimal:2',
        'insert_price_per_m' => 'decimal:2',
        'spotlight_price' => 'decimal:2',
        'chandelier_price' => 'decimal:2',
        'pipe_price' => 'decimal:2',
        'curtain_niche_price' => 'decimal:2',
        'cornice_price_per_m' => 'decimal:2',
        'ventilation_hole_price' => 'decimal:2',
        'mounting_price_per_m2' => 'decimal:2',
        'additional_cost' => 'decimal:2',
        'sketch_crop' => 'array',
        'sketch_recognition' => 'array',
        'sketch_recognized_at' => 'datetime',
        'last_calculated_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_IN_PROGRESS => 'В работе',
            self::STATUS_READY => 'Готово',
        ];
    }

    public static function materialOptions(): array
    {
        return [
            'pvc' => 'ПВХ',
            'fabric' => 'Ткань',
            'hybrid' => 'Комбинированный',
        ];
    }

    public static function textureOptions(): array
    {
        return [
            'matte' => 'Матовый',
            'satin' => 'Сатиновый',
            'gloss' => 'Глянцевый',
            'fabric' => 'Тканевый',
        ];
    }

    public static function defaultEstimateRates(): array
    {
        return [
            'canvas_price_per_m2' => 950,
            'profile_price_per_m' => 180,
            'insert_price_per_m' => 45,
            'spotlight_price' => 350,
            'chandelier_price' => 550,
            'pipe_price' => 250,
            'curtain_niche_price' => 1400,
            'cornice_price_per_m' => 280,
            'ventilation_hole_price' => 250,
            'mounting_price_per_m2' => 450,
            'additional_cost' => 0,
        ];
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function measurement()
    {
        return $this->belongsTo(Measurement::class);
    }

    public function rooms()
    {
        return $this->hasMany(CeilingProjectRoom::class)->orderBy('sort_order')->orderBy('id');
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
