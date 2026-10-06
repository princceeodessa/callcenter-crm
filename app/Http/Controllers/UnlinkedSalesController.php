<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\StockMovement;
use App\Models\WarehouseItem;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Склад → «Продажи без списания».
 *
 * Продажи, отмеченные «списано со склада», но без пары со склада (так загрузилась история продаж
 * 07.07.2026): остатки по ним не уменьшились, и склад показывает пары, которых уже нет.
 * Для каждой продажи — подсказки (название + закупочная цена из файла) и кнопки «Списать эту пару»
 * или «Списывать не нужно». Привязку можно отменить. Здесь же — отрицательные остатки: «Обнулить».
 */
class UnlinkedSalesController extends Controller
{
    /** Слова, по которым модели не различить. */
    private const NOISE = ['shoes', 'shoe', 'casual', 'sneakers', 'sneaker', 'кроссовки', 'продажа', 'the', 'and', 'x', 'р'];

    public function index()
    {
        $user = Auth::user();
        $accId = $user->account_id;

        $pending = Deal::where('account_id', $accId)->soldWithoutStock()
            ->orderBy('stock_deducted_at')->orderBy('id')->get();

        $items = WarehouseItem::where('account_id', $accId)
            ->orderBy('brand')->orderBy('model')->orderBy('size')->get();
        $inStock = $items->filter(fn (WarehouseItem $i) => (int) $i->quantity > 0)->values();
        $itemTokens = $inStock->mapWithKeys(fn (WarehouseItem $i) => [$i->id => self::tokens($i->brand.' '.$i->model)]);

        $rows = $pending->map(fn (Deal $d) => [
            'deal' => $d,
            'suggestions' => $this->suggest($d, $inStock, $itemTokens),
        ]);

        $linked = Deal::where('account_id', $accId)
            ->whereNotNull('stock_linked_at')->whereNotNull('warehouse_item_id')->whereNull('returned_at')
            ->with('warehouseItem')->orderByDesc('stock_linked_at')->orderByDesc('id')->get();
        $skipped = Deal::where('account_id', $accId)
            ->whereNotNull('stock_link_skipped_at')->whereNull('warehouse_item_id')->whereNull('returned_at')
            ->orderByDesc('stock_link_skipped_at')->orderByDesc('id')->get();

        $negative = $items->filter(fn (WarehouseItem $i) => (int) $i->quantity < 0)->values();
        $negativeMoves = $negative->isEmpty() ? collect() : StockMovement::whereIn('warehouse_item_id', $negative->pluck('id'))
            ->with('user:id,name')->orderByDesc('id')->get()
            ->groupBy('warehouse_item_id')->map(fn (Collection $m) => $m->take(3));

        return view('warehouse.unlinked-sales', [
            'rows' => $rows,
            'inStock' => $inStock,
            'linked' => $linked,
            'skipped' => $skipped,
            'negative' => $negative,
            'negativeMoves' => $negativeMoves,
            'isHead' => $user->role === 'sneaker_head',
        ]);
    }

    /** «Списать эту пару»: привязать пару к продаже и уменьшить остаток. Дата продажи и закупочная цена — из файла. */
    public function link(Request $request, Deal $deal, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $data = $request->validate([
            'item_id' => ['nullable', 'integer'],
            'item_ref' => ['nullable', 'string', 'max:500'],
        ]);
        $itemId = (int) ($data['item_id'] ?? 0);
        if (! $itemId && preg_match('/#(\d+)/', (string) ($data['item_ref'] ?? ''), $m)) {
            $itemId = (int) $m[1];
        }
        $item = $itemId ? WarehouseItem::where('account_id', $user->account_id)->whereKey($itemId)->first() : null;
        if (! $item) {
            return back()->withErrors(['item_ref' => "Продажа #{$deal->id}: выберите пару из списка (начните вводить название)."]);
        }
        if (! $deal->stock_deducted_at || $deal->warehouse_item_id || $deal->returned_at) {
            return back()->with('status', "Продажа #{$deal->id} уже разобрана.");
        }

        $warehouse->deductLegacySale($deal, $item);
        $left = (int) $item->fresh()->quantity;
        $this->note($deal, "Сверка склада: к продаже привязана пара «{$item->display_name}» и списана со склада (осталось {$left}).");

        return back()->with('status', "Продажа #{$deal->id}: списана пара «{$item->display_name}», на складе осталось {$left}.");
    }

    /** «Списывать не нужно»: пары не было на складе в CRM или её уже списали вручную. */
    public function skip(Deal $deal)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);
        if (! $deal->stock_deducted_at || $deal->warehouse_item_id || $deal->returned_at || $deal->stock_link_skipped_at) {
            return back()->with('status', "Продажа #{$deal->id} уже разобрана.");
        }

        $deal->forceFill(['stock_link_skipped_at' => now()])->save();
        $this->note($deal, 'Сверка склада: списывать со склада не нужно (пары не было на складе в CRM или её уже списали вручную).');

        return back()->with('status', "Продажа #{$deal->id}: отмечено «списывать не нужно».");
    }

    /** Вернуть продажу в список: отменить «списывать не нужно» или привязку пары (пара возвращается на склад). */
    public function undo(Deal $deal, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        if ($deal->stock_linked_at && $deal->warehouse_item_id) {
            $name = $deal->warehouseItem?->display_name ?? 'пара';
            $warehouse->unlinkLegacySale($deal);
            $this->note($deal, "Сверка склада: привязка пары «{$name}» отменена, пара возвращена на склад.");

            return back()->with('status', "Продажа #{$deal->id}: списание отменено, «{$name}» вернулась на склад.");
        }
        if ($deal->stock_link_skipped_at && ! $deal->warehouse_item_id) {
            $deal->forceFill(['stock_link_skipped_at' => null])->save();
            $this->note($deal, 'Сверка склада: отметка «списывать не нужно» снята.');

            return back()->with('status', "Продажа #{$deal->id} снова в списке.");
        }

        return back();
    }

    /** Отрицательный остаток → 0 (меньше нуля пар не бывает: это ошибка учёта). */
    public function zero(WarehouseItem $item, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);
        if ((int) $item->quantity >= 0) {
            return back();
        }

        $warehouse->zeroNegative($item, 'Сверка склада: отрицательный остаток обнулён');

        return back()->with('status', "Остаток «{$item->display_name}» обнулён.");
    }

    /**
     * Подсказки к продаже: пары в наличии, где совпадает бренд/модель (по словам названия),
     * выше — те, у которых закупочная цена равна цене из файла продаж.
     */
    private function suggest(Deal $deal, Collection $inStock, Collection $itemTokens): Collection
    {
        $words = self::tokens((string) $deal->title);
        if ($words === []) {
            return collect();
        }
        $cost = $deal->sold_unit_cost !== null ? (float) $deal->sold_unit_cost : null;

        $candidates = $inStock
            ->map(function (WarehouseItem $item) use ($words, $cost, $itemTokens) {
                $hits = self::hits($words, $itemTokens[$item->id] ?? []);
                $sameCost = $cost !== null && $item->avg_cost !== null && abs((float) $item->avg_cost - $cost) < 0.5;

                // Совпавшая цена весит как два слова: «nike + та же цена» не обгоняет «nike air max 1» с другой ценой.
                return ['item' => $item, 'hits' => $hits, 'same_cost' => $sameCost, 'score' => $hits + ($sameCost ? 2 : 0)];
            })
            ->filter(fn (array $s) => $s['hits'] > 0);

        // Нашлась модель (бренд + слово модели) — пары, совпавшие только брендом, не показываем: это шум.
        $best = (float) $candidates->max('hits');
        if ($best >= 2) {
            $candidates = $candidates->filter(fn (array $s) => $s['hits'] >= 2);
        }

        return $candidates
            // При равном счёте выше та, где совпало больше слов названия: цена может совпасть случайно.
            ->sort(fn (array $a, array $b) => [$b['score'], $b['hits'], $a['item']->id] <=> [$a['score'], $a['hits'], $b['item']->id])
            ->take(8)
            ->values();
    }

    /** @return string[] слова названия в нижнем регистре, без «шума». */
    private static function tokens(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($parts, fn (string $w) => ! in_array($w, self::NOISE, true))));
    }

    /**
     * Сколько слов продажи нашлось в названии пары. Опечатки в словах (blazzer/blazer, lagerfield/lagerfeld)
     * засчитываются, в артикулах и числах — нет: DV0833 и DV0834 — разные модели.
     * Короткие числа («1» в «book 1», «air force 1») — полслова: они есть у многих моделей.
     */
    private static function hits(array $words, array $itemWords): float
    {
        $hits = 0.0;
        foreach ($words as $w) {
            $fuzzy = strlen($w) >= 5 && preg_match('/^\p{L}+$/u', $w);
            foreach ($itemWords as $iw) {
                if ($w === $iw || ($fuzzy && strlen($iw) >= 5 && preg_match('/^\p{L}+$/u', $iw) && levenshtein($w, $iw) <= 1)) {
                    $hits += preg_match('/^\d{1,2}$/', $w) ? 0.5 : 1.0;
                    break;
                }
            }
        }

        return $hits;
    }

    private function note(Deal $deal, string $body): void
    {
        DealActivity::create([
            'account_id' => $deal->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => Auth::id(),
            'type' => 'system',
            'body' => $body,
        ]);
    }
}
