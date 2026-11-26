<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Ensure the authenticated user is active, belongs to an active branch (if branch-scoped),
     * and holds one of the allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user('web');

        if (! $user || ! $user->is_active) {
            abort(403, 'Akun Anda tidak aktif atau tidak memiliki akses.');
        }

        if ($user->branch_id !== null) {
            $branchActive = $user->relationLoaded('branch')
                ? (bool) $user->branch?->is_active
                : (bool) $user->branch()->value('is_active');

            if (! $branchActive) {
                abort(403, 'Cabang KOPDES Anda sedang dinonaktifkan.');
            }
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        if (! empty($allowedRoles) && ! in_array($user->role, $allowedRoles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk sumber daya ini.');
        }

        return $next($request);
    }
}
