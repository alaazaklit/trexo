<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Sets the app locale from the {locale} route segment for the duration
     * of this request only — the global config('app.locale') is left alone
     * so nothing outside the public marketing routes changes behavior.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $locales = config('localization.locales');

        if (!array_key_exists($locale, $locales)) {
            abort(404);
        }

        $meta = $locales[$locale];

        App::setLocale($meta['locale']);

        View::share('currentLocale', $locale);
        View::share('currentLocaleMeta', $meta);
        View::share('dir', $meta['dir']);

        return $next($request);
    }
}
