<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplaceMemberCardRequest;
use App\Http\Requests\ResetMemberPinRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Member;
use App\Services\ActivityLogService;
use App\Services\MemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Display a listing of cooperative members scoped to the authenticated user's region/branch.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('viewAny', Member::class);

        $branchFilter = $request->filled('home_branch_id') ? (int) $request->query('home_branch_id') : null;
        $statusFilter = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        Gate::authorize('filter-region', [
            null,
            null,
            $branchFilter,
        ]);

        $query = Member::forUser($actor)
            ->with(['homeBranch:id,code,name'])
            ->orderByDesc('id');

        if ($branchFilter !== null) {
            $query->where('home_branch_id', $branchFilter);
        }

        if (in_array($statusFilter, ['active', 'inactive'], true)) {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('member_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('card_uid', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $members = $query->get()->map(fn (Member $member) => [
            'id' => $member->id,
            'member_code' => $member->member_code,
            'home_branch_id' => $member->home_branch_id,
            'card_uid' => $member->card_uid,
            'name' => $member->name,
            'phone' => $member->phone,
            'nik' => $member->nik,
            'status' => $member->status,
            'has_portal_pin' => ! empty($member->pin_hash),
            'joined_at' => $member->joined_at?->format('Y-m-d'),
            'home_branch' => $member->homeBranch,
            'can_update' => $actor->can('update', $member),
        ]);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Members/Index', [
            'members' => $members,
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'home_branch_id' => $branchFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
            'permissions' => [
                'can_create' => $actor->can('create', Member::class),
                'is_admin_nasional' => $actor->isAdminNasional(),
            ],
        ]);
    }

    /**
     * Show the form for registering a new cooperative member.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();

        Gate::authorize('create', Member::class);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Members/Form', [
            'member' => null,
            'branches' => $branches,
            'defaultBranchId' => $actor->isAdminCabang() ? $actor->branch_id : ($branches->first()?->id ?? ''),
            'isBranchLocked' => $actor->isAdminCabang(),
        ]);
    }

    /**
     * Store a newly registered member in storage.
     */
    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $member = $this->memberService->create($request->user(), $request->validated());

        return redirect()
            ->route('members.index')
            ->with('success', "Member {$member->name} ({$member->member_code}) berhasil didaftarkan.");
    }

    /**
     * Show the form for editing the specified member.
     */
    public function edit(Request $request, Member $member): Response
    {
        $actor = $request->user();

        Gate::authorize('update', $member);

        $branches = Branch::forUser($actor)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Members/Form', [
            'member' => [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'home_branch_id' => $member->home_branch_id,
                'card_uid' => $member->card_uid,
                'name' => $member->name,
                'phone' => $member->phone ?? '',
                'nik' => $member->nik ?? '',
                'status' => $member->status,
                'joined_at' => $member->joined_at?->format('Y-m-d'),
            ],
            'branches' => $branches,
            'defaultBranchId' => $member->home_branch_id,
            'isBranchLocked' => $actor->isAdminCabang(),
        ]);
    }

    /**
     * Update the specified member in storage.
     */
    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $updated = $this->memberService->update($request->user(), $member, $request->validated());

        return redirect()
            ->route('members.index')
            ->with('success', "Data member {$updated->name} ({$updated->member_code}) berhasil diperbarui.");
    }

    /**
     * Replace a member's lost/damaged RFID card.
     */
    public function replaceCard(ReplaceMemberCardRequest $request, Member $member): RedirectResponse
    {
        $oldCardUid = $member->card_uid;
        $updated = $this->memberService->replaceCard($member, (string) $request->validated('card_uid'));

        $this->activityLogService->record(
            action: ActivityLog::ACTION_MEMBER_CARD_REPLACED,
            description: "Mengganti kartu RFID member {$updated->name} ({$updated->member_code})",
            actor: $request->user(),
            subject: $updated,
            oldValues: ['card_uid' => $oldCardUid],
            newValues: ['card_uid' => $updated->card_uid],
        );

        return redirect()
            ->back()
            ->with('success', "Kartu RFID member {$updated->name} berhasil diganti ke UID {$updated->card_uid}.");
    }

    /**
     * Toggle a member's active/inactive status.
     */
    public function toggleStatus(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $oldStatus = $member->status;
        $updated = $this->memberService->toggleStatus($member);
        $statusLabel = $updated->status === 'active' ? 'diaktifkan' : 'dinonaktifkan';

        $this->activityLogService->record(
            action: ActivityLog::ACTION_MEMBER_STATUS_TOGGLED,
            description: "Status member {$updated->name} ({$updated->member_code}) {$statusLabel}",
            actor: $request->user(),
            subject: $updated,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $updated->status],
        );

        return redirect()
            ->back()
            ->with('success', "Kartu member {$updated->name} ({$updated->member_code}) berhasil {$statusLabel}.");
    }

    /**
     * Set or reset a member's 6-digit Portal Member PIN (FR-P3-09).
     * Never stores raw PIN values in activity_logs.
     */
    public function resetPin(ResetMemberPinRequest $request, Member $member): RedirectResponse
    {
        $hadPinBefore = ! empty($member->pin_hash);
        $updated = $this->memberService->resetPin($member, (string) $request->validated('pin'));
        $verb = $hadPinBefore ? 'direset' : 'diatur';

        $this->activityLogService->record(
            action: ActivityLog::ACTION_MEMBER_PIN_RESET,
            description: "PIN Portal Member {$updated->name} ({$updated->member_code}) {$verb}",
            actor: $request->user(),
            subject: $updated,
            oldValues: ['has_portal_pin' => $hadPinBefore],
            newValues: ['has_portal_pin' => true],
        );

        return redirect()
            ->back()
            ->with('success', "PIN Portal Member {$updated->name} ({$updated->member_code}) berhasil {$verb}.");
    }
}
