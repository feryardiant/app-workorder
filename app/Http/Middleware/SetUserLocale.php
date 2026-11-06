<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
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

        $locale = $user instanceof User
            ? $user->locale?->value
            : null;

        $locale ??= (string) config('app.default_locale');

        app()->setLocale($locale);
        Date::setLocale($locale);

        return $next($request);
    }
}
