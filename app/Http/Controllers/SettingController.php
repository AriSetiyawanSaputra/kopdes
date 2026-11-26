<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppSettingsRequest;
use App\Models\AppSetting;
use App\Services\AppSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private readonly AppSettingService $appSettingService,
    ) {}

    /**
     * Tampilkan halaman Pengaturan Sistem Dinamis (khusus Admin Nasional) (GET /settings).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AppSetting::class);

        $all = $this->appSettingService->all();
        $publicConfig = $this->appSettingService->publicConfig();

        return Inertia::render('Settings/Index', [
            'settings' => array_merge($all, [
                'institution_logo_url' => $publicConfig['institution_logo_url'],
            ]),
        ]);
    }

    /**
     * Simpan perubahan Pengaturan Sistem Dinamis & bersihkan cache (PUT /settings).
     */
    public function update(UpdateAppSettingsRequest $request): RedirectResponse
    {
        $this->appSettingService->updateMany(
            data: $request->validated(),
            actor: $request->user(),
            logo: $request->file('logo'),
            removeLogo: $request->boolean('remove_logo'),
        );

        return redirect()
            ->route('settings.index')
            ->with('success', 'Pengaturan sistem KOPDES berhasil disimpan dan langsung diterapkan.');
    }
}
