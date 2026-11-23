<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Member;
use App\Models\User;
use App\Support\CardUidNormalizer;
use Illuminate\Support\Facades\DB;

class MemberService
{
    /**
     * Register a new cooperative member with automatic home_branch_id and MBR-{BRANCH_CODE}-{SEQ5} member_code.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Member
    {
        return DB::transaction(function () use ($actor, $data): Member {
            $branchId = $actor->isAdminCabang()
                ? (int) $actor->branch_id
                : (int) $data['home_branch_id'];

            $branch = Branch::findOrFail($branchId);
            $memberCode = $this->generateMemberCode($branch);

            return Member::create([
                'member_code' => $memberCode,
                'home_branch_id' => $branch->id,
                'card_uid' => CardUidNormalizer::normalize((string) $data['card_uid']),
                'name' => trim((string) $data['name']),
                'phone' => ! empty($data['phone']) ? trim((string) $data['phone']) : null,
                'nik' => ! empty($data['nik']) ? trim((string) $data['nik']) : null,
                'status' => $data['status'] ?? 'active',
                'joined_at' => ! empty($data['joined_at']) ? $data['joined_at'] : now()->toDateString(),
            ]);
        });
    }

    /**
     * Update an existing cooperative member.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Member $member, array $data): Member
    {
        return DB::transaction(function () use ($actor, $member, $data): Member {
            $branchId = $actor->isAdminCabang()
                ? (int) $actor->branch_id
                : (int) ($data['home_branch_id'] ?? $member->home_branch_id);

            $member->update([
                'home_branch_id' => $branchId,
                'card_uid' => CardUidNormalizer::normalize((string) $data['card_uid']),
                'name' => trim((string) $data['name']),
                'phone' => ! empty($data['phone']) ? trim((string) $data['phone']) : null,
                'nik' => ! empty($data['nik']) ? trim((string) $data['nik']) : null,
                'status' => $data['status'] ?? $member->status,
                'joined_at' => ! empty($data['joined_at']) ? $data['joined_at'] : $member->joined_at,
            ]);

            return $member->refresh();
        });
    }

    /**
     * Replace a lost/damaged RFID card with a newly bound card_uid.
     */
    public function replaceCard(Member $member, string $rawCardUid): Member
    {
        $member->update([
            'card_uid' => CardUidNormalizer::normalize($rawCardUid),
        ]);

        return $member->refresh();
    }

    /**
     * Toggle member card status between 'active' and 'inactive'.
     */
    public function toggleStatus(Member $member): Member
    {
        $member->update([
            'status' => $member->status === 'active' ? 'inactive' : 'active',
        ]);

        return $member->refresh();
    }

    /**
     * Generate unique member_code with format MBR-{BRANCH_CODE}-{SEQUENCE_5_DIGIT}.
     */
    public function generateMemberCode(Branch $branch): string
    {
        $prefix = "MBR-{$branch->code}-";

        $existingCodes = Member::query()
            ->where('member_code', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('member_code');

        $maxSeq = 0;

        foreach ($existingCodes as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $code, $matches)) {
                $seq = (int) $matches[1];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }

        $nextSeq = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
            $exists = Member::where('member_code', $candidate)->exists();
            $nextSeq++;
        } while ($exists);

        return $candidate;
    }
}
