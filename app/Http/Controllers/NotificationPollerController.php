<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPollerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $seenKey = "notifications.seen.{$user->id}";
        $pendingKey = "notifications.pending.{$user->id}";

        /** @var array<int, string>|null $seen */
        $seen = $request->session()->get($seenKey);

        $unread = $user->unreadNotifications()->latest()->get();
        $unreadIds = $unread->pluck('id')->all();

        /** @var array<int, string> $pending */
        $pending = [];
        $newest = null;

        if ($seen === null) {
            $request->session()->put($seenKey, $unreadIds);
            $request->session()->put($pendingKey, []);
        } else {
            /** @var array<int, string> $storedPending */
            $storedPending = $request->session()->get($pendingKey, []);

            $pending = array_values(array_intersect($storedPending, $unreadIds));

            if ($pending === []) {
                $pending = $unread->whereNotIn('id', $seen)->pluck('id')->values()->all();
            }

            $newest = $pending === [] ? null : $unread->firstWhere('id', $pending[0]);

            $request->session()->put($pendingKey, $pending);
        }

        return response()->json([
            'notification' => $newest === null ? null : [
                'id' => $newest->id,
                'title' => $newest->data['title'] ?? __('Notification'),
                'url' => route('notifications.index', ['open' => $newest->id]),
            ],
            'pending' => $pending,
            'count' => $user->unreadNotifications()->count(),
            'text' => match (true) {
                \count($pending) > 1 => __('You have :count new notifications.', ['count' => \count($pending)]),
                $newest !== null => $newest->data['description'] ?? __('You have a new notification.'),
                default => __('You have a new notification.'),
            },
            'linkLabel' => __('Open notification'),
            'closeLabel' => __('Close notification'),
        ]);
    }

    public function ack(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<int, string> $ids */
        $ids = $request->validate([
            'ids' => ['array'],
            'ids.*' => ['string'],
        ])['ids'] ?? [];

        $seenKey = "notifications.seen.{$user->id}";
        $pendingKey = "notifications.pending.{$user->id}";

        /** @var array<int, string> $pending */
        $pending = $request->session()->get($pendingKey, []);
        /** @var array<int, string> $seen */
        $seen = $request->session()->get($seenKey, []);

        $acked = array_values(array_intersect($pending, $ids));

        $request->session()->put($seenKey, array_values(array_unique([...$seen, ...$acked])));
        $request->session()->put($pendingKey, array_values(array_diff($pending, $acked)));

        return response()->json(['ok' => true]);
    }
}
