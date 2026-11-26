<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberCardController extends Controller
{
    public const MAX_BATCH_CARDS = 20;

    /**
     * Cetak kartu member fisik satuan (ID-1 85.6mm x 53.98mm dengan QR Code) (GET /members/{member}/card).
     */
    public function show(Request $request, Member $member): Response
    {
        Gate::authorize('view', $member);

        $member->load(['homeBranch:id,code,name,address,phone']);

        return Inertia::render('Members/PrintCards', [
            'members' => [$this->formatCardPayload($member)],
            'isBatch' => false,
        ]);
    }

    /**
     * Cetak batch kartu member fisik (maksimal 20 kartu per sesi cetak) (GET /members/print-cards).
     */
    public function batch(Request $request): Response
    {
        $actor = $request->user();
        Gate::authorize('viewAny', Member::class);

        $requestedIds = collect((array) $request->query('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($requestedIds->isNotEmpty()) {
            $members = Member::query()
                ->with(['homeBranch:id,code,name,address,phone'])
                ->whereIn('id', $requestedIds->take(self::MAX_BATCH_CARDS))
                ->orderBy('member_code')
                ->get();

            // Anti-IDOR: setiap member yang diminta wajib berada di dalam scope wilayah aktor
            foreach ($members as $member) {
                Gate::authorize('view', $member);
            }
        } else {
            $members = Member::forUser($actor)
                ->with(['homeBranch:id,code,name,address,phone'])
                ->where('status', 'active')
                ->orderBy('member_code')
                ->limit(self::MAX_BATCH_CARDS)
                ->get();
        }

        return Inertia::render('Members/PrintCards', [
            'members' => $members->map(fn (Member $m) => $this->formatCardPayload($m))->values()->all(),
            'isBatch' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCardPayload(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_code' => $member->member_code,
            'name' => $member->name,
            'phone' => $member->phone,
            'status' => $member->status,
            'has_rfid_card' => (bool) $member->has_rfid_card,
            'joined_at' => $member->joined_at?->format('Y-m-d'),
            'home_branch' => $member->homeBranch ? [
                'id' => $member->homeBranch->id,
                'code' => $member->homeBranch->code,
                'name' => $member->homeBranch->name,
            ] : null,
        ];
    }
}
