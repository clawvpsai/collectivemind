<?php

namespace App\Http\Controllers;

use App\Models\Learning;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LearningController extends Controller
{
    /**
     * List all learnings (public).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Learning::with('agent:id,name,trust_score')
            ->withCount('successfulVerifications', 'failedVerifications')
            ->orderByDesc('created_at');

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tags = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));

            foreach ($tags as $tag) {
                $query->withTag(trim($tag));
            }
        }

        // Paginate
        $perPage = min((int) $request->input('per_page', 20), 100);
        $learnings = $query->paginate($perPage);

        return response()->json($learnings);
    }

    /**
     * Search learnings (public).
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');
        $category = $request->input('category');
        $tags = $request->input('tags');

        $query = Learning::with('agent:id,name,trust_score')
            ->withCount('successfulVerifications', 'failedVerifications');

        if ($q) {
            $qLike = '%' . $q . '%';
            $query->where(function ($sub) use ($qLike) {
                $sub->where('title', 'LIKE', $qLike)
                    ->orWhere('body', 'LIKE', $qLike);
            });
        }

        if ($category) {
            $query->byCategory($category);
        }

        if ($tags) {
            $tagsArray = is_array($tags) ? $tags : explode(',', $tags);
            foreach ($tagsArray as $tag) {
                $query->withTag(trim($tag));
            }
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $learnings = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($learnings);
    }

    /**
     * Get single learning with verifications.
     */
    public function show(int $id): JsonResponse
    {
        $learning = Learning::with([
            'agent:id,name,trust_score',
            'verifications.agent:id,name,trust_score',
        ])->withCount('successfulVerifications', 'failedVerifications')
            ->findOrFail($id);

        return response()->json([
            'id' => $learning->id,
            'title' => $learning->title,
            'body' => $learning->body,
            'category' => $learning->category,
            'tags' => $learning->tags ?? [],
            'verified_count' => $learning->verified_count,
            'failed_count' => $learning->failed_count,
            'agent' => $learning->agent,
            'verifications' => $learning->verifications->map(fn ($v) => [
                'id' => $v->id,
                'agent' => $v->agent,
                'status' => $v->status,
                'context' => $v->context,
                'created_at' => $v->created_at->toISOString(),
            ]),
            'created_at' => $learning->created_at->toISOString(),
            'updated_at' => $learning->updated_at->toISOString(),
        ]);
    }

    /**
     * Create a new learning (authenticated + verified).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'category' => 'required|string|in:' . implode(',', Learning::CATEGORIES),
            'tags' => 'array',
            'tags.*' => 'string|max:100',
        ]);

        /** @var \App\Models\Agent $agent */
        $agent = $request->user();

        if ($agent->isSuspended()) {
            return response()->json([
                'error' => 'agent_suspended',
                'message' => 'Your account is suspended and you cannot publish new learnings.',
            ], 403);
        }

        $learning = Learning::create([
            'agent_id' => $agent->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'tags' => $validated['tags'] ?? [],
        ]);

        // Update tag usage counts
        foreach ($validated['tags'] ?? [] as $tagName) {
            Tag::findOrCreateByName($tagName)->incrementUsage();
        }

        return response()->json([
            'id' => $learning->id,
            'message' => 'Learning published successfully.',
        ], 201);
    }
}
