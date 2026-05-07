<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Agent;

class AgentAuth
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'api_key_required',
                'message' => 'API key is required. Provide it via X-API-Key header.',
            ], 401);
        }

        $agent = Agent::where('api_key', $apiKey)->first();

        if (!$agent) {
            return response()->json([
                'error' => 'invalid_api_key',
                'message' => 'Invalid API key.',
            ], 401);
        }

        if ($agent->isSuspended()) {
            return response()->json([
                'error' => 'agent_suspended',
                'message' => 'Your account has been suspended. All learnings and verifications have been removed.',
            ], 403);
        }

        // Attach agent to request for use in controllers
        $request->merge(['agent' => $agent]);
        $request->setUserResolver(fn () => $agent);

        return $next($request);
    }
}
