<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // In production, pin every generated URL (route(), url(), signed links,
        // sitemap, canonicals) to the configured APP_URL origin regardless of
        // the incoming Host header. With trustProxies('*') the request host is
        // CDN/attacker-controlled, so without this a request to www./apex/an IP
        // vhost would emit self-referencing canonicals on the wrong host and
        // split indexing signals. Left untouched in local/testing so dev on
        // :8123 and the test suite keep using the request host.
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
