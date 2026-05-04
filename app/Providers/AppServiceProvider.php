<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('vendor.pagination.admin');
        Paginator::defaultSimpleView('vendor.pagination.simple-admin');

        $this->app->setLocale($this->resolveLocale());
    }

    private function resolveLocale(): string
    {
        try {
            if (!Schema::hasTable('settings')) {
                return 'en';
            }

            $locale = (string) (DB::table('settings')
                ->where('setting_key', 'default_locale')
                ->value('setting_value') ?? 'en');

            return match (strtolower(str_replace('_', '-', trim($locale)))) {
                'ar', 'ar-sd' => 'ar',
                default => 'en',
            };
        } catch (Throwable $exception) {
            return 'en';
        }
    }
}
