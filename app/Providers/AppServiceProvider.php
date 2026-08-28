<?php

namespace App\Providers;

use App\Services\GeminiClient;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GeminiClient::class, fn () => new GeminiClient(
            (string) config('services.gemini.key'),
            (string) config('services.gemini.model'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->symbols());
    }
}
