<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WarehouseProduct extends Model
{
    use BelongsToAccount;

    protected $fillable = [
        'account_id', 'brand', 'model', 'article', 'custom_name', 'image_path',
    ];

    protected static function bootWarehouseProduct(): void
    {
        static::creating(function (WarehouseProduct $p) {
            if (empty($p->article)) {
                $p->article = self::nextArticle($p->account_id);
            }
        });
        static::saving(function (WarehouseProduct $p) {
            if (empty($p->article)) {
                $p->article = self::nextArticle($p->account_id);
            }
        });
    }

    public function photos()
    {
        return $this->hasMany(WarehouseProductPhoto::class)->orderBy('sort')->orderBy('id');
    }

    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->custom_name)) {
            return $this->custom_name;
        }
        $n = trim(($this->brand ?? '').' '.($this->model ?? ''));
        return $n !== '' ? $n : 'Без названия';
    }

    /** Все фото (галерея). Первое — обложка. Legacy image_path подставляется если галерея пуста. */
    public function getGalleryAttribute(): array
    {
        $items = [];
        foreach ($this->photos as $photo) {
            $items[] = ['id' => $photo->id, 'url' => Storage::disk('public')->url($photo->path)];
        }
        if (empty($items) && ! empty($this->image_path)) {
            $items[] = ['id' => null, 'url' => Storage::disk('public')->url($this->image_path)];
        }
        return $items;
    }

    /** URL обложки (первое фото) или null. */
    public function getImageUrlAttribute(): ?string
    {
        $g = $this->gallery;
        return $g[0]['url'] ?? null;
    }

    /**
     * Сгенерировать следующий короткий артикул K-00001, K-00002, …
     * Уникален в пределах аккаунта.
     */
    public static function nextArticle(int $accountId): string
    {
        $max = static::query()
            ->where('account_id', $accountId)
            ->where('article', 'like', 'K-%')
            ->selectRaw("MAX(CAST(SUBSTRING(article, 3) AS UNSIGNED)) as m")
            ->value('m');
        $next = ((int) $max) + 1;
        return sprintf('K-%05d', $next);
    }
}
