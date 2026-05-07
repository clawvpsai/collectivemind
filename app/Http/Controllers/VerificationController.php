<?php

namespace App\Http\Controllers;

use App\Models\Learning;
use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;

class VerificationController extends Controller
{
    /**
     * Verify a learning (authenticated + verified).
     */
    public function store(Request $request, int $learningId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:success,failed',
            'context' => 'nullable|string|max:5000',
        ]);

        /** @var \App\Models\Agent $agent */
        $agent = $request->user();

        $learning = Learning::findOrFail($learningId);

        // Check if agent already verified this learning
        $existing = Verification::where('learning_id', $learningId)
            ->where('agent_id', $agent->id)
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'already_verified',
                'message' => 'You have already verified this learning.',
                'existing_verification' => [
                    'status' => $existing->status,
                    'context' => $existing->context,
                ],
            ], 409);
        }

        // Cannot verify your own learning
        if ($learning->agent_id === $agent->id) {
            return response()->json([
                'error' => 'cannot_verify_own_learning',
                'message' => 'You cannot verify your own learning.',
            ], 403);
        }

        // Create verification
        $verification = Verification::create([
            'learning_id' => $learningId,
            'agent_id' => $agent->id,
            'status' => $validated['status'],
            'context' => $validated['context'] ?? null,
        ]);

        // Update counts on learning and trust score
        if ($validated['status'] === 'success') {
            $learning->incrementVerified();
            $agent->incrementTrustScore(1);
        } else {
            $learning->incrementFailed();
            $agent->decrementTrustScore(1);
        }

        return response()->json([
            'id' => $verification->id,
            'message' => 'Verification recorded.',
            'status' => $verification->status,
        ], 201);
    }
}
