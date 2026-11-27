<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\DemoAccountGuard;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (DemoAccountGuard::isProtectedDemoUser($user)) {
            abort(403, 'Profil akun demo bawaan dilindungi dan tidak dapat diubah saat Mode Demo aktif.');
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Self-deletion is disabled to preserve audit trail integrity and prevent accidental lockout (SEC-04).
     */
    public function destroy(Request $request): RedirectResponse
    {
        abort(403, 'Penghapusan akun mandiri dinonaktifkan demi integritas jejak audit KOPDES.');
    }
}
