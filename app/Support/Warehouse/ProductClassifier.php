<?php

namespace App\Support\Warehouse;

/**
 * Автоопределение категории / пола / сезона товара по названию (бренд + модель).
 * Эвристика — не идеальна, но лучше пустых полей. Ручная правка перезаписывает.
 */
class ProductClassifier
{
    /** Ключевые слова: слово => метка. Порядок первого совпадения = приоритет. */
    private const GENDER_PATTERNS = [
        'kids'   => ['KIDS', ' GS', ' TD', ' PS', 'CHILDREN', 'ДЕТСК'],
        'female' => ["WOMEN'S", 'WOMENS', 'WMNS', 'WOMEN ', 'GIRL', 'ЖЕНСК'],
        'male'   => ["MEN'S", 'MENS', 'MAN ', 'MALE', 'МУЖСК'],
    ];

    private const CATEGORY_PATTERNS = [
        'winter' => ['GORE-TEX', 'GORETEX', 'WINTERIZED', 'WATERPROOF', 'TIMBERLAND', 'MOON BOOT', 'ЗИМН', 'МЕХ'],
        'premium' => ['OFF WHITE', 'OFF-WHITE', 'BALENCIAGA', 'DIOR', 'GUCCI', 'PRADA', 'LOUIS VUITTON', 'LV ', 'YEEZY', 'JORDAN', 'TRAVIS'],
        'sport' => [
            'AIR MAX', 'AIR FORCE', 'AIRFORCE', 'DUNK', 'BLAZER', 'CORTEZ',
            'ULTRABOOST', 'BOOST', 'PEGASUS', 'ZOOM', 'REACT',
            'SAMBA', 'SUPERSTAR', 'STAN SMITH', 'GAZELLE', 'CAMPUS',
            'RUNNING', 'RUNNER', 'TRAINER', 'BASKETBALL', 'FOOTBALL', 'СПОРТИВН',
        ],
        'casual' => [
            'SUEDE', 'CLASSIC', 'CLASSICS', 'ORIGINALS', 'LIFESTYLE', 'LOAFER',
            'MULE', 'SLIP-ON', 'SLIP ON', 'HERITAGE', 'RETRO',
        ],
    ];

    private const SEASON_PATTERNS = [
        'winter' => ['GORE-TEX', 'GORETEX', 'WINTERIZED', 'WATERPROOF', 'ЗИМН', 'МЕХ', 'TIMBERLAND', 'MOON BOOT'],
        'summer' => ['SANDAL', 'SLIDE', 'SLIP-ON', 'MULE', 'ЛЕТН', 'CANVAS'],
    ];

    public static function guessAll(string $brand, string $model, ?string $customName = null): array
    {
        $text = mb_strtoupper(trim($brand.' '.$model.' '.($customName ?: '')));

        return [
            'category' => self::firstMatch($text, self::CATEGORY_PATTERNS),
            'gender' => self::firstMatch($text, self::GENDER_PATTERNS) ?: 'unisex',
            'season' => self::firstMatch($text, self::SEASON_PATTERNS) ?: 'demi',
        ];
    }

    public static function categoryOptions(): array
    {
        return [
            'sport' => '🏃 Спортивные',
            'casual' => '👟 Повседневные',
            'premium' => '💎 Премиум',
            'winter' => '❄️ Зимние',
            'kids' => '🧒 Детские',
        ];
    }

    public static function genderOptions(): array
    {
        return [
            'male' => '👨 Мужские',
            'female' => '👩 Женские',
            'unisex' => '⚦ Унисекс',
            'kids' => '🧒 Детские',
        ];
    }

    public static function seasonOptions(): array
    {
        return [
            'summer' => '☀️ Лето',
            'winter' => '❄️ Зима',
            'demi' => '🍂 Демисезон',
        ];
    }

    private static function firstMatch(string $upperText, array $patterns): ?string
    {
        foreach ($patterns as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($upperText, $needle)) {
                    return $label;
                }
            }
        }
        return null;
    }
}
