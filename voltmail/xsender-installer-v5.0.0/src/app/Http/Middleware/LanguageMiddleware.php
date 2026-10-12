<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Language;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     * Uses caching to avoid database queries on every request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $fallback = config('app.locale', 'en');

        try {
            $lang_code = $request->header('X-App-Language') ?? session('locale');

            // Ensure lang_code is a string (guard against corrupted session data)
            if ($lang_code && !is_string($lang_code)) {
                $lang_code = null;
                session()->forget('locale');
            }

            // If we have a valid locale in session, use it without DB query
            if ($lang_code && is_string($lang_code) && session('locale') === $lang_code) {
                App::setLocale($lang_code);
                return $next($request);
            }

            // Get available languages from cache (avoid DB query)
            $languages = $this->getCachedLanguages($fallback);

            if ($lang_code && isset($languages[$lang_code])) {
                App::setLocale($lang_code);
                session(['locale' => $lang_code]);
                return $next($request);
            }

            // Get default language from cache
            $defaultLang = $this->getDefaultLanguage($fallback);
            App::setLocale($defaultLang);
            session(['locale' => $defaultLang]);

        } catch (\Exception $e) {
            // Database not available (fresh install) - use config default
            App::setLocale($fallback);
        }

        return $next($request);
    }

    /**
     * Get languages from cache
     */
    private function getCachedLanguages(string $fallback): array
    {
        return Cache::remember('available_languages', now()->addHours(24), function () use ($fallback) {
            try {
                return Language::where('status', StatusEnum::TRUE->status())
                    ->pluck('code', 'code')
                    ->toArray();
            } catch (\Exception $e) {
                return [$fallback => $fallback];
            }
        });
    }

    /**
     * Get default language code
     */
    private function getDefaultLanguage(string $fallback): string
    {
        static $defaultLang = null;

        if ($defaultLang !== null) {
            return $defaultLang;
        }

        $cached = Cache::remember('default_language', now()->addHours(24), function () use ($fallback) {
            try {
                return Language::where('is_default', StatusEnum::TRUE->status())
                    ->value('code') ?? $fallback;
            } catch (\Exception $e) {
                return $fallback;
            }
        });

        // Guard against corrupted cache (e.g., a Model stored by another provider)
        $defaultLang = is_string($cached) ? $cached : $fallback;

        return $defaultLang;
    }
}
