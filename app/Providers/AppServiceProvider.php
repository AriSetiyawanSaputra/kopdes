<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Member;
use App\Models\StockMovement;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\MemberPolicy;
use App\Policies\ReportPolicy;
use App\Policies\StockPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(BranchStock::class, StockPolicy::class);
        Gate::policy(StockMovement::class, StockPolicy::class);

        Gate::define('view-dashboard', [ReportPolicy::class, 'viewDashboard']);
        Gate::define('view-sales-report', [ReportPolicy::class, 'viewSalesReport']);
        Gate::define('view-member-report', [ReportPolicy::class, 'viewMemberReport']);
        Gate::define('filter-region', [ReportPolicy::class, 'filterRegion']);
    }
}
