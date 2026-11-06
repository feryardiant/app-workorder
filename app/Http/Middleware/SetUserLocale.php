<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Set the application locale from the authenticated user's preference.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof HasLocalePreference) {
            $locale = $user->preferredLocale();

            app()->setLocale($locale);
            Date::setLocale($locale);
        }

        return $next($request);
    }
}
