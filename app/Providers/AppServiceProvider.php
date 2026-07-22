<?php

namespace App\Providers;

use App\Services\SettingService;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'pt_BR'));

        View::composer(['layouts.site', 'site.*'], SiteLayoutComposer::class);
    }
}
