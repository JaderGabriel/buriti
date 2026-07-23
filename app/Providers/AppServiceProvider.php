<?php

namespace App\Providers;

use App\Services\SettingService;
use App\View\Composers\AdminLayoutComposer;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'pt_BR'));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        View::composer(['layouts.site', 'site.*'], SiteLayoutComposer::class);
        View::composer('layouts.admin', AdminLayoutComposer::class);
    }
}
