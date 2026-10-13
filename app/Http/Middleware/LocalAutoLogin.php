<?php

namespace App\Http\Middleware;

use Closure;
use Database\Seeders\LocalTestUserSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local testing only: opening the app signs you straight in as the seeded
 * test user, so there is no login screen to get past.
 *
 * Two locks, both required: APP_ENV must be "local" AND LOCAL_AUTO_LOGIN=true.
 * A production .env has neither, so this is inert on a real server even if
 * the flag were copied across by mistake.
 *
 * To look at the signed-out pages (home, login, register), set
 * LOCAL_AUTO_LOGIN=false - logging out just signs you back in otherwise.
 */
class LocalAutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') && config('app.local_auto_login') && Auth::guest()) {
            $user = \App\Models\User::where('email', LocalTestUserSeeder::EMAIL)->first();

            if (! $user) {
                Artisan::call('db:seed', ['--class' => LocalTestUserSeeder::class, '--force' => true]);
                $user = \App\Models\User::where('email', LocalTestUserSeeder::EMAIL)->first();
            }

            if ($user) {
                Auth::login($user, remember: true);
                $request->session()->regenerate();
            }
        }

        return $next($request);
    }
}
