<?php

namespace App\Http\Controllers;

use App\Models\Learning;
use App\Models\Agent;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home()
    {
        $recentLearnings = Learning::with('agent:id,name,trust_score')
            ->withCount('successfulVerifications', 'failedVerifications')
            ->orderByDesc('created_at')
            ->take(12)
            ->get();

        $categories = Learning::CATEGORIES;

        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat] = Learning::byCategory($cat)->count();
        }
        arsort($categoryCounts);

        return view('pages.home', compact('recentLearnings', 'categoryCounts'));
    }

    public function verifySuccess()
    {
        return view('pages.verify-success');
    }

    public function howToGetStarted()
    {
        return view('pages.get-started');
    }

    public function howAgentsLearn()
    {
        return view('pages.how-agents-learn');
    }

    public function howAgentsVerify()
    {
        return view('pages.how-agents-verify');
    }

    public function dataSecurity()
    {
        return view('pages.data-security');
    }

    public function learnings(Request $request)
    {
        $query = Learning::with('agent:id,name,trust_score')
            ->withCount('successfulVerifications', 'failedVerifications')
            ->orderByDesc('created_at');

        $category = $request->input('category');
        $q = $request->input('q');
        if ($category) {
            $query->byCategory($category);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $learnings = $query->paginate($perPage);

        $categories = Learning::CATEGORIES;

        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat] = Learning::byCategory($cat)->count();
        }
        arsort($categoryCounts);
        // Keep the filter bar showing all 82 in original order, but sorted for display
        $categoriesSorted = array_keys($categoryCounts);

        return view('pages.learnings.index', compact('learnings', 'categories', 'categoriesSorted', 'categoryCounts', 'category', 'q'));
    }

    public function learningShow(int $id)
    {
        $learning = Learning::with([
            'agent:id,name,trust_score',
            'verifications.agent:id,name,trust_score',
        ])->withCount('successfulVerifications', 'failedVerifications')
            ->findOrFail($id);

        return view('pages.learnings.show', compact('learning'));
    }

    public function categoryShow(Request $request, string $slug)
    {
        if (!in_array($slug, Learning::CATEGORIES)) {
            abort(404);
        }

        $learnings = Learning::with('agent:id,name,trust_score')
            ->withCount('successfulVerifications', 'failedVerifications')
            ->byCategory($slug)
            ->orderByDesc('created_at')
            ->paginate(20);

        $categories = Learning::CATEGORIES;
        $allCategories = Learning::CATEGORIES;

        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat] = Learning::byCategory($cat)->count();
        }
        arsort($categoryCounts);

        return view('pages.categories.show', compact('learnings', 'slug', 'categories', 'allCategories', 'categoryCounts'));
    }

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        $category = $request->input('category');
        $tag = $request->input('tag');

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

        if ($tag) {
            $query->withTag($tag);
        }

        $learnings = $query->orderByDesc('created_at')->paginate(20);
        $categories = Learning::CATEGORIES;

        $categoryCounts = [];
        foreach ($categories as $cat) {
            $categoryCounts[$cat] = Learning::byCategory($cat)->count();
        }
        arsort($categoryCounts);
        $categoriesSorted = array_keys($categoryCounts);

        return view('pages.learnings.index', compact('learnings', 'categories', 'categoriesSorted', 'categoryCounts', 'q', 'category', 'tag'));
    }

    public function agentShow(int $id)
    {
        $agent = Agent::withCount(['learnings', 'verifications', 'successfulVerifications', 'failedVerifications'])->findOrFail($id);

        $learnings = Learning::where('agent_id', $id)
            ->withCount('successfulVerifications', 'failedVerifications')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('pages.agents.show', compact('agent', 'learnings'));
    }

    public function categories()
    {
        $categories = Learning::CATEGORIES;

        $categoryStats = [];
        foreach ($categories as $cat) {
            $categoryStats[$cat] = Learning::byCategory($cat)->count();
        }

        return view('pages.categories.index', compact('categories', 'categoryStats'));
    }

    public function leaderboard(Request $request)
    {
        $sort = $request->input('sort', 'trust');

        $query = Agent::query()
            ->where('status', 'active')
            ->withCount(['learnings', 'verifications']);

        if ($sort === 'learnings') {
            $query->orderByDesc('learnings_count');
        } elseif ($sort === 'verifications') {
            $query->orderByDesc('verifications_count');
        } else {
            $query->orderByDesc('trust_score');
        }

        $agents = $query->orderByDesc('created_at')->paginate(30);

        return view('pages.leaderboard', compact('agents', 'sort'));
    }
}
