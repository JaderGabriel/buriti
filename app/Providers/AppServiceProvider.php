<?php

namespace App\Providers;

use App\Services\SettingService;
use App\Support\SubdirectoryRouteCacheGuard;
use App\View\Composers\AdminLayoutComposer;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);

        // Must run before routes boot — route:cache + APP_URL subpath breaks GET /.
        SubdirectoryRouteCacheGuard::clearIncompatibleCache();
    }

    public function boot(): void
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        date_default_timezone_set($timezone);
        Carbon::setLocale(config('app.locale', 'pt_BR'));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        View::composer(['layouts.site', 'site.*'], SiteLayoutComposer::class);
        View::composer('layouts.admin', AdminLayoutComposer::class);

        if ($this->app->runningInConsole()) {
            Event::listen(CommandFinished::class, function (CommandFinished $event): void {
                if (! in_array($event->command, ['route:cache', 'optimize', 'optimize:clear'], true)) {
                    return;
                }

                if (! SubdirectoryRouteCacheGuard::clearIncompatibleCache()) {
                    return;
                }

                $event->output->writeln(
                    '<comment>Route cache removido: incompatível com APP_URL em subpath (ex. /public). GET / voltaria a falhar com 405.</comment>'
                );
            });
        }
    }
}
