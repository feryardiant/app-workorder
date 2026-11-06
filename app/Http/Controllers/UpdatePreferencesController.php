<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdatePreferencesController extends Controller
{
    /**
     * Update the authenticated user's locale and timezone preferences.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'locale' => ['sometimes', 'required', Rule::enum(Locale::class)],
            'timezone' => ['sometimes', 'required', 'timezone'],
        ]);

        abort_if($validated === [], 422, __('No preference provided.'));

        $user->update($validated);

        return response()->json([
            'locale' => $user->locale?->value,
            'timezone' => $user->timezone,
        ]);
    }
}
