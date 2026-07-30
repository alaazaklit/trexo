<?php

use Illuminate\Support\Facades\Request;

if (!function_exists('localized_route')) {
    /**
     * route() helper that always injects the current (or given) locale
     * segment, so Blade views never have to pass it manually.
     */
    function localized_route(string $name, array $params = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return route($name, array_merge(['locale' => $locale], $params));
    }
}

if (!function_exists('alternate_locale_url')) {
    /**
     * Swaps the leading {locale} segment of the current request path for
     * the given locale, keeping the rest of the path/query intact. Used by
     * the language switcher and hreflang tags.
     */
    function alternate_locale_url(string $locale): string
    {
        $segments = explode('/', trim(Request::path(), '/'));

        if (!empty($segments) && array_key_exists($segments[0], config('localization.locales'))) {
            $segments[0] = $locale;
        } else {
            array_unshift($segments, $locale);
        }

        $path = implode('/', $segments);
        $query = Request::getQueryString();

        return url($path).($query ? '?'.$query : '');
    }
}
