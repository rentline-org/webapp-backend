<?php

namespace App\Providers;

use App\Helpers\OrganizationHelper;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Register any application services. */
    public function register(): void
    {
        //
    }

    /** Bootstrap any application services. */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(maxAttempts: config('app.rate_limit_max_attempts_per_minute', 1000))
                ->by($request->user()?->id ?: $request->ip());
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        $this->app->scoped(ActiveOrganizationContext::class, function () {
            return new ActiveOrganizationContext;
        });

        // $this->app->singleton(OrganizationHelper::class, function () {
        //     return new OrganizationHelper;
        // });
    }
}
