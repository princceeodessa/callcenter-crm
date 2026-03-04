<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();

        $deals = Deal::query()
            ->with(['contact','stage','responsible'])
            ->when($q, function ($query) use ($q) {
                $query->where('title','like',"%{$q}%")
                    ->orWhereHas('contact', fn($c) => $c->where('phone','like',"%{$q}%")->orWhere('name','like',"%{$q}%"));
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('deals.index', compact('deals','q'));
    }

    public function kanban()
    {
        $stages = PipelineStage::query()
            ->orderBy('sort')
            ->get();

        $dealsByStage = Deal::query()
            ->with(['contact','responsible'])
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('stage_id');

        return view('deals.kanban', compact('stages','dealsByStage'));
    }

    public function create()
    {
        $stages = PipelineStage::query()->orderBy('sort')->get();
        return view('deals.create', compact('stages'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'contact_name' => ['nullable','string','max:255'],
            'contact_phone' => ['nullable','string','max:32'],
            'stage_id' => ['required','exists:pipeline_stages,id'],
        ]);

        $user = Auth::user();

        $contact = null;
        if (!empty($data['contact_name']) || !empty($data['contact_phone'])) {
            $contact = Contact::firstOrCreate(
                ['account_id' => $user->account_id, 'phone' => $data['contact_phone']],
                ['name' => $data['contact_name']]
            );
        }

        $stage = PipelineStage::findOrFail($data['stage_id']);

        $deal = Deal::create([
            'account_id' => $user->account_id,
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'title' => $data['title'],
            'contact_id' => $contact?->id,
            'responsible_user_id' => $user->id,
        ]);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'system',
            'body' => 'Сделка создана вручную',
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => null,
            'to_stage_id' => $stage->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return redirect()->route('deals.show', $deal);
    }

    public function show(Deal $deal)
    {
        $deal->load([
            'contact',
            'stage',
            'responsible',
            'tasks' => fn($q) => $q->orderBy('status')->orderBy('due_at'),
            'activities' => fn($q) => $q->orderByDesc('id'),
            'conversations' => fn($q) => $q->with('lastMessage')->orderByDesc('last_message_at'),
        ]);
        $stages = PipelineStage::query()->orderBy('sort')->get();
        return view('deals.show', compact('deal','stages'));
    }

    public function changeStage(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'stage_id' => ['required','exists:pipeline_stages,id']
        ]);

        $user = Auth::user();
        $to = PipelineStage::findOrFail($data['stage_id']);
        $fromId = $deal->stage_id;

        if ($to->pipeline_id !== $deal->pipeline_id) {
            abort(422, 'Stage pipeline mismatch');
        }

        $deal->stage_id = $to->id;
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'stage_changed',
            'body' => 'Стадия изменена',
            'payload' => ['from_stage_id' => $fromId, 'to_stage_id' => $to->id],
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => $fromId,
            'to_stage_id' => $to->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return back();
    }

    /** Kanban drag&drop move. */
    public function move(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'to_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $user = Auth::user();
        if ($deal->account_id !== $user->account_id) {
            abort(403);
        }

        $to = PipelineStage::findOrFail($data['to_stage_id']);
        if ($to->pipeline_id !== $deal->pipeline_id) {
            return response()->json(['ok' => false, 'message' => 'Stage pipeline mismatch'], 422);
        }

        $fromId = $deal->stage_id;
        if ((int)$fromId === (int)$to->id) {
            return response()->json(['ok' => true]);
        }

        $deal->stage_id = $to->id;
        $deal->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'stage_changed',
            'body' => 'Стадия изменена (перетаскиванием)',
            'payload' => ['from_stage_id' => $fromId, 'to_stage_id' => $to->id],
        ]);

        DealStageHistory::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'from_stage_id' => $fromId,
            'to_stage_id' => $to->id,
            'changed_by_user_id' => $user->id,
            'changed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}