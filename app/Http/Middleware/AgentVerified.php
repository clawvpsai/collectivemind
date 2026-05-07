<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AgentVerified
{
    public function handle(Request $request, Closure $next)
    {
        /** @var \App\Models\Agent $agent */
        $agent = $request->user();

        if (!$agent) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Authentication required.',
            ], 401);
        }

        if (!$agent->isActive()) {
            $emailSentMinutesAgo = $agent->created_at
                ? now()->diffInMinutes($agent->created_at)
                : 0;

            return response()->json([
                'error' => 'email_not_verified',
                'message' => "Email not verified. Verification email sent {$emailSentMinutesAgo} minutes ago.",
                'resend_link' => '/api/agent/resend-verification',
            ], 403);
        }

        return $next($request);
    }
}
