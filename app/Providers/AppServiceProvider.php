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
        // Search boxes: what the user types is matched literally. A plain
        // LIKE '%{$s}%' treated % and _ as wildcards, so searching "%" listed
        // every record. '!' is the escape character: unlike a backslash it
        // reads the same on MySQL (any SQL mode) and SQLite.
        \Illuminate\Database\Query\Builder::macro('whereLiteralLike', function (string $column, ?string $value, string $boolean = 'and') {
            $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $value) . '%';

            return $this->whereRaw($this->getGrammar()->wrap($column) . " LIKE ? ESCAPE '!'", [$pattern], $boolean);
        });
        \Illuminate\Database\Query\Builder::macro('orWhereLiteralLike', function (string $column, ?string $value) {
            return $this->whereLiteralLike($column, $value, 'or');
        });

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
