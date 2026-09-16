<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request->header('Accept-Language'));

        app()->setLocale($locale);
        Carbon::setLocale($locale === 'pt-BR' ? 'pt_BR' : $locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(?string $acceptLanguage): string
    {
        $supported = config('app.supported_locales', ['en']);

        foreach (explode(',', $acceptLanguage ?? '') as $preference) {
            $language = mb_strtolower(trim(explode(';', $preference)[0]));
            $normalized = match (true) {
                $language === 'pt', str_starts_with($language, 'pt-'), str_starts_with($language, 'pt_') => 'pt-BR',
                $language === 'en', str_starts_with($language, 'en-'), str_starts_with($language, 'en_') => 'en',
                default => null,
            };

            if ($normalized !== null && in_array($normalized, $supported, true)) {
                return $normalized;
            }
        }

        return config('app.fallback_locale', config('app.locale', 'en'));
    }
}
