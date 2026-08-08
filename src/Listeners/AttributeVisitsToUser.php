<?php

namespace IbrahimKaya\VisitTracker\Listeners;

use IbrahimKaya\VisitTracker\Models\PageVisitLog;
use Illuminate\Support\Facades\Log;

class AttributeVisitsToUser
{
    /**
     * Handle Login / Registered events and attach anonymous visits to the user.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! config('visit-tracker.attribute_on_auth', true)) {
            return;
        }

        $user = $event->user ?? null;

        if (! $user) {
            return;
        }

        try {
            $cookieName = config('visit-tracker.visitor_cookie', 'visit_tracker_vid');
            $visitorId = request()->cookie($cookieName);
            $sessionId = session()->getId();

            PageVisitLog::attributeToUser(
                $user->getAuthIdentifier(),
                $visitorId ?: null,
                $sessionId ?: null
            );
        } catch (\Throwable $e) {
            Log::error('VisitTracker attribution error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
