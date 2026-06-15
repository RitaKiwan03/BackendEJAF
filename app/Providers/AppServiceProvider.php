<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // ✅ حد عام لكل طلبات /api/* — 60 طلب/دقيقة لكل IP
        // (المسارات الحساسة مثل /auth/login و /auth/captcha لديها
        //  حدود أشد مُعرَّفة مباشرة في routes/api.php عبر throttle:5,1 إلخ)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
