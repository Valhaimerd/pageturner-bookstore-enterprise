<?php

namespace App\Providers;

use App\Models\BackupMonitoring;
use App\Models\Book;
use App\Observers\BookObserver;
use App\Services\AuditLogger;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\Providers\FakeAIProvider;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\CleanupWasSuccessful;
use Spatie\Backup\Events\HealthyBackupWasFound;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app) {
            return match ((string) config('ai.provider', 'ollama')) {
                'fake' => $app->make(FakeAIProvider::class),
                default => $app->make(OllamaProvider::class),
            };
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        Book::observe(BookObserver::class);
        $this->registerAuthAuditListeners();
        $this->registerBackupListeners();
    }

    protected function configureRateLimiters(): void
    {
        RateLimiter::for('public-api', fn (Request $request) => $this->makeTieredLimits($request, 'public', 30, 3));
        RateLimiter::for('auth-api', fn (Request $request) => $this->makeTieredLimits(
            $request,
            $request->user()?->isAdmin() ? 'admin' : ($request->user()?->isPremium() ? 'premium' : 'standard'),
            $request->user()?->isAdmin() ? 1000 : ($request->user()?->isPremium() ? 300 : 60),
            $request->user()?->isAdmin() ? 50 : ($request->user()?->isPremium() ? 20 : 6),
        ));
        RateLimiter::for('auth-sensitive', fn (Request $request) => $this->makeTieredLimits($request, 'auth', 10, 2));
        RateLimiter::for('ai-public', fn (Request $request) => $this->makeTieredLimits($request, 'ai', 12, 2));
    }

    protected function makeTieredLimits(Request $request, string $tier, int $perMinute, int $perSecond): array
    {
        $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
        $response = function (Request $request, array $headers) use ($tier, $perMinute) {
            return response()->json([
                'message' => 'Rate limit exceeded.',
                'tier' => $tier,
                'limit' => $perMinute,
                'retry_after' => $headers['Retry-After'] ?? null,
            ], 429, $headers + [
                'X-RateLimit-Tier' => $tier,
            ]);
        };

        return [
            Limit::perMinute($perMinute)->by($key.':minute')->response($response),
            Limit::perSecond($perSecond)->by($key.':second')->response($response),
        ];
    }

    protected function registerAuthAuditListeners(): void
    {
        $logger = $this->app->make(AuditLogger::class);

        $this->app['events']->listen(Login::class, fn (Login $event) => $logger->userEvent($event->user, 'logged_in', request: request()));
        $this->app['events']->listen(Logout::class, fn (Logout $event) => $logger->userEvent($event->user, 'logged_out', request: request()));
        $this->app['events']->listen(Registered::class, fn (Registered $event) => $logger->userEvent($event->user, 'registered', request: request()));
        $this->app['events']->listen(Verified::class, fn (Verified $event) => $logger->userEvent($event->user, 'email_verified', request: request()));
        $this->app['events']->listen(PasswordReset::class, fn (PasswordReset $event) => $logger->userEvent($event->user, 'password_reset', request: request()));
        $this->app['events']->listen(Failed::class, fn (Failed $event) => $logger->userEvent($event->user, 'login_failed', request: request()));
        $this->app['events']->listen(Lockout::class, function (Lockout $event) use ($logger): void {
            $user = \App\Models\User::where('email', request('email'))->first();
            $logger->userEvent($user, 'login_locked', request: request());
        });
    }

    protected function registerBackupListeners(): void
    {
        $events = $this->app['events'];

        $events->listen(BackupWasSuccessful::class, fn (BackupWasSuccessful $event) => BackupMonitoring::create([
            'event' => 'backup_run',
            'status' => 'success',
            'disk' => $event->backupDestination->diskName(),
            'message' => 'Backup completed successfully.',
            'happened_at' => now(),
        ]));

        $events->listen(BackupHasFailed::class, fn (BackupHasFailed $event) => BackupMonitoring::create([
            'event' => 'backup_run',
            'status' => 'failed',
            'disk' => $event->backupDestination?->diskName(),
            'message' => $event->exception->getMessage(),
            'happened_at' => now(),
        ]));

        $events->listen(CleanupWasSuccessful::class, fn () => BackupMonitoring::create([
            'event' => 'backup_cleanup',
            'status' => 'success',
            'message' => 'Backup cleanup completed successfully.',
            'happened_at' => now(),
        ]));

        $events->listen(CleanupHasFailed::class, fn (CleanupHasFailed $event) => BackupMonitoring::create([
            'event' => 'backup_cleanup',
            'status' => 'failed',
            'message' => $event->exception->getMessage(),
            'happened_at' => now(),
        ]));

        $events->listen(HealthyBackupWasFound::class, fn () => BackupMonitoring::create([
            'event' => 'backup_monitor',
            'status' => 'healthy',
            'message' => 'Backup monitor reported a healthy state.',
            'happened_at' => now(),
        ]));

        $events->listen(UnhealthyBackupWasFound::class, fn (UnhealthyBackupWasFound $event) => BackupMonitoring::create([
            'event' => 'backup_monitor',
            'status' => 'unhealthy',
            'message' => $event->backupDestinationStatus->backupDestination()->backupName(),
            'happened_at' => now(),
        ]));
    }
}
