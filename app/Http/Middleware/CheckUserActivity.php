<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\UserDevice;

class CheckUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Try to get Visitor-Id from header
        $visitorId = $request->header('Visitor-Id');

        // Fallback: get active device for this user
        if (!$visitorId) {
            $device = UserDevice::where('user_id', $user->id)
                ->where('is_active', 1)
                ->latest()
                ->first();
        } else {
            $device = UserDevice::where('visitor_id', $visitorId)
                ->where('user_id', $user->id)
                ->where('is_active', 1)
                ->first();
        }

        if (!$device) {
            return response()->json([
                'status' => false,
                'message' => 'Session not valid'
            ], 401);
        }

        // 🔥 Inactivity check (30 minutes)
        if (
            $device->last_activity_at &&
            Carbon::parse($device->last_activity_at)->lt(now()->subMinutes(30))
        ) {

            // Delete only current token
            $request->user()->currentAccessToken()?->delete();

            $device->update([
                'is_active' => 0
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Session expired due to inactivity'
            ], 401);
        }

        // Update activity
        $device->update([
            'last_activity_at' => now()
        ]);

        return $next($request);
    }
}