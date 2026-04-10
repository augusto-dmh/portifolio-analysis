<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\SubmissionPolicy;
use App\Policies\UserPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->registerAuthorization();
        $this->configureRateLimiting();
    }

    private function registerAuthorization(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Submission::class, SubmissionPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);

        Gate::define('admin', fn (User $user): bool => $user->role === UserRole::Admin);

        Gate::define('analyst-or-above', fn (User $user): bool => in_array($user->role, [
            UserRole::Admin,
            UserRole::Analyst,
        ], true));

        Gate::define('viewer-or-above', fn (User $user): bool => $user->role instanceof UserRole);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('submission-uploads', function (Request $request) {
            return Limit::perMinute(
                (int) config('portfolio.upload.rate_limit_per_minute', 10),
            )->by((string) ($request->user()?->getAuthIdentifier() ?? 'guest'));
        });
    }
}
