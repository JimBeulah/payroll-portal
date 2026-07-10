<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show the user's push notification settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/notifications', [
            'hasPushSubscription' => $request->user()->pushSubscriptions()->exists(),
        ]);
    }
}
