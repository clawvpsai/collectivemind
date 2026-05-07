<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Mail\AgentVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class AgentController extends Controller
{
    /**
     * Register a new agent.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:agents,email',
        ]);

        $agent = Agent::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'api_key' => Agent::generateApiKey(),
            'verification_token' => Agent::generateVerificationToken(),
            'status' => 'pending',
        ]);

        // Send verification email
        Mail::to($agent->email)->send(new AgentVerification($agent));

        return response()->json([
            'agent_id' => $agent->id,
            'api_key' => $agent->api_key,
            'status' => $agent->status,
            'message' => 'Agent registered. Please check your email to verify.',
        ], 201);
    }

    /**
     * Verify agent email via token.
     */
    public function verify(string $token): \Illuminate\Http\RedirectResponse
    {
        $agent = Agent::where('verification_token', $token)->firstOrFail();

        $agent->update([
            'status' => 'active',
            'email_verified_at' => now(),
            'verification_token' => null,
        ]);

        return redirect(config('app.url') . '/verify-success');
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|integer|exists:agents,id',
        ]);

        $agent = Agent::findOrFail($validated['agent_id']);

        if ($agent->isActive()) {
            return response()->json([
                'message' => 'Agent already verified.',
            ], 400);
        }

        // Generate new token and resend email
        $agent->update(['verification_token' => Agent::generateVerificationToken()]);
        Mail::to($agent->email)->send(new AgentVerification($agent));

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    /**
     * Get current authenticated agent profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->user();

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'status' => $agent->status,
            'trust_score' => $agent->trust_score,
            'email_verified_at' => $agent->email_verified_at?->toISOString(),
            'created_at' => $agent->created_at->toISOString(),
        ]);
    }

    /**
     * Get public agent profile.
     */
    public function show(int $id): JsonResponse
    {
        $agent = Agent::withCount(['learnings', 'verifications'])->findOrFail($id);

        return response()->json([
            'id' => $agent->id,
            'name' => $agent->name,
            'trust_score' => $agent->trust_score,
            'learnings_count' => $agent->learnings_count,
            'verifications_count' => $agent->verifications_count,
            'created_at' => $agent->created_at->toISOString(),
        ]);
    }

    /**
     * Get all agents (public list).
     */
    public function index(): JsonResponse
    {
        $agents = Agent::where('status', 'active')
            ->withCount(['learnings', 'verifications'])
            ->orderBy('trust_score', 'desc')
            ->get();

        return response()->json([
            'data' => $agents->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'status' => $a->status,
                'trust_score' => $a->trust_score,
                'learnings_count' => $a->learnings_count,
                'verifications_count' => $a->verifications_count,
                'created_at' => $a->created_at->toISOString(),
            ]),
        ]);
    }
}
