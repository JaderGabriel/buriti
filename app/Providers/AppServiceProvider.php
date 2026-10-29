<?php

namespace App\Providers;

use App\Models\JirauCard;
use App\Models\Opportunity;
use App\Observers\OpportunityObserver;
use App\Services\ImapMailboxStore;
use App\Services\MailboxStore;
use App\Services\MailSettingsService;
use App\Services\SettingService;
use App\Support\RuntimeVersions;
use App\Support\SubdirectoryRouteCacheGuard;
use App\View\Composers\AdminLayoutComposer;
use App\View\Composers\SiteLayoutComposer;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
        $this->app->singleton(MailboxStore::class, ImapMailboxStore::class);

        // Must run before routes boot — route:cache + APP_URL subpath breaks GET /.
        SubdirectoryRouteCacheGuard::clearIncompatibleCache();
    }

    public function boot(): void
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        date_default_timezone_set($timezone);
        Carbon::setLocale(config('app.locale', 'pt_BR'));

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        View::composer('*', function ($view): void {
            $view->with('product', RuntimeVersions::product());
        });
        View::composer(['layouts.site', 'site.*'], SiteLayoutComposer::class);
        View::composer(['layouts.admin', 'layouts.jirau'], AdminLayoutComposer::class);

        Opportunity::observe(OpportunityObserver::class);

        try {
            $this->app->make(MailSettingsService::class)->apply();
        } catch (Throwable) {
            // Base pode ainda não existir (migrate / primeiro boot).
        }

        Route::bind('item', function (string $value, $route) {
            $card = $route->parameter('card');
            if (! $card instanceof JirauCard) {
                $card = JirauCard::query()->find($card);
            }

            if ($card instanceof JirauCard) {
                return $card->checklistItems()->whereKey($value)->firstOrFail();
            }

            abort(404);
        });

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
