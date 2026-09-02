<?php

namespace App\Providers;

use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\HttpLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LotteryDrawProviderResolver::class, static fn (): LotteryDrawProviderResolver => new LotteryDrawProviderResolver([
            'fake' => new FakeLotteryDrawProvider,
            'elboletoganador' => new HttpLotteryDrawProvider(
                baseUrl: (string) config('lottery-api.base_url'),
                apiKey: (string) config('lottery-api.key'),
                timeoutSeconds: (int) config('lottery-api.timeout_seconds'),
                connectTimeoutSeconds: (int) config('lottery-api.connect_timeout_seconds'),
            ),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', static function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
