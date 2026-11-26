<?php

namespace App\Http\Middleware;

use App\Services\AppSettingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user('web');
        $member = $request->user('member');

        if ($user) {
            $user->loadMissing([
                'province:id,name',
                'regency:id,name',
                'branch:id,code,name,village_id,district_id,regency_id,province_id,address,phone,is_active',
            ]);
        }

        if ($member) {
            $member->loadMissing([
                'homeBranch:id,code,name',
            ]);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'province_id' => $user->province_id,
                    'regency_id' => $user->regency_id,
                    'branch_id' => $user->branch_id,
                    'is_active' => (bool) $user->is_active,
                    'province' => $user->province ? [
                        'id' => $user->province->id,
                        'name' => $user->province->name,
                    ] : null,
                    'regency' => $user->regency ? [
                        'id' => $user->regency->id,
                        'name' => $user->regency->name,
                    ] : null,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'code' => $user->branch->code,
                        'name' => $user->branch->name,
                        'village_id' => $user->branch->village_id,
                        'district_id' => $user->branch->district_id,
                        'regency_id' => $user->branch->regency_id,
                        'province_id' => $user->branch->province_id,
                        'address' => $user->branch->address,
                        'phone' => $user->branch->phone,
                        'is_active' => (bool) $user->branch->is_active,
                    ] : null,
                ] : null,
                'member' => $member ? [
                    'id' => $member->id,
                    'member_code' => $member->member_code,
                    'name' => $member->name,
                    'phone' => $member->phone,
                    'home_branch_id' => $member->home_branch_id,
                    'home_branch_name' => $member->homeBranch?->name,
                    'home_branch_code' => $member->homeBranch?->code,
                    'has_rfid_card' => (bool) $member->has_rfid_card,
                    'status' => $member->status,
                    'joined_at' => $member->joined_at?->format('Y-m-d'),
                ] : null,
                'pos_locked' => (bool) $request->session()->get('pos_locked', false),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app_settings' => fn () => app(AppSettingService::class)->publicConfig(),
            'app' => [
                'demo_mode' => (bool) config('kopdes.demo_mode', false),
            ],
        ];
    }
}
