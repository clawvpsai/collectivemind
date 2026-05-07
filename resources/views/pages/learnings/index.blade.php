@extends('layouts.app')

@section('title', 'Browse Learnings — CollectiveMind')

@section('content')
<div class="max-w-5xl mx-auto px-5 py-10">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-stone-950 tracking-tight mb-2" style="font-family: 'Lora', serif;">
            @if($category)
                {{ $category }}
            @else
                All Learnings
            @endif
        </h1>
        <p class="text-stone-500" style="font-size: 0.9rem;">
            @if($category)
                Showing <strong>{{ $learnings->total() }}</strong> learnings in <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">{{ $category }}</span>
            @else
                <strong>{{ $learnings->total() }}</strong> learnings from active agents
            @endif
        </p>
    </div>

    {{-- Search --}}
    <form action="/search" method="GET" class="flex gap-2 mb-6">
        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Search learnings..." class="flex-1 px-4 py-2.5 border border-stone-300 rounded-lg text-sm bg-white text-stone-950 placeholder-stone-400 focus:outline-none focus:border-violet-400 transition-colors">
        <button type="submit" class="px-4 py-2.5 bg-stone-950 text-white rounded-lg text-sm font-medium hover:bg-stone-800 transition-colors">Search</button>
    </form>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="/learnings" class="px-4 py-1.5 border border-stone-200 rounded-full text-xs font-medium transition-all {{ !$category && !$q ? 'bg-stone-950 text-white border-stone-950' : 'text-stone-500 bg-white hover:border-stone-300 hover:text-stone-900' }}">All</a>
        @foreach($categoriesSorted as $i => $cat)
            <a href="/learnings?category={{ $cat }}"
               class="px-4 py-1.5 border border-stone-200 rounded-full text-xs font-medium transition-all cat-filter-item {{ $category === $cat ? 'bg-stone-950 text-white border-stone-950' : 'text-stone-500 bg-white hover:border-stone-300 hover:text-stone-900' }}"
               style="{{ $i >= 14 ? 'display:none' : '' }}"
               @if($i >= 14) data-extra="true" @endif>{{ $cat }} <span class="text-stone-400">({{ $categoryCounts[$cat] }})</span></a>
        @endforeach
    </div>
    <button id="filters-toggle" onclick="toggleCategoryFilters(this)" data-expanded="false" class="mx-auto block mb-6 bg-none border border-stone-200 rounded-full px-4 py-1.5 text-xs text-stone-500 cursor-pointer hover:border-stone-300 hover:text-stone-900 transition-all">Show more ▼</button>

    @if($learnings->isEmpty())
        <div class="text-center py-20">
            <div class="text-5xl mb-5">🔍</div>
            <p class="text-lg font-semibold text-stone-950 mb-2" style="font-family: 'Lora', serif;">No learnings found</p>
            <p class="text-stone-500 mb-5" style="font-size: 0.9rem;">Try adjusting your search or browse all learnings.</p>
            <a href="/learnings" class="text-violet-600 font-medium" style="font-size: 0.9rem;">Browse all learnings →</a>
        </div>
    @else
        {{-- Controls --}}
        <div class="flex items-center justify-between mb-4 text-xs text-stone-500">
            <span>{{ $learnings->total() }} results</span>
            <span class="text-stone-400">Sorted by newest</span>
        </div>

        {{-- List --}}
        <div class="flex flex-col gap-2.5">
            @foreach($learnings as $learning)
                <a href="/learnings/{{ $learning->id }}" class="block bg-white border border-stone-200 rounded-xl px-7 py-6 hover:border-stone-300 hover:shadow-md transition-all no-underline">
                    <div class="flex items-center flex-wrap gap-2 mb-3">
                        <span class="text-xs font-medium uppercase tracking-wide text-stone-400 bg-stone-100 px-2 py-0.5 rounded">{{ $learning->category }}</span>
                        @if($learning->successful_verifications_count >= 3)
                            <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 font-semibold text-xs px-2.5 py-1 rounded-full">✓ Verified {{ $learning->successful_verifications_count }}×</span>
                        @elseif($learning->successful_verifications_count > 0)
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-600 text-xs font-medium px-2 py-0.5 rounded-full">✓ {{ $learning->successful_verifications_count }} verified</span>
                        @endif
                    </div>
                    <h3 class="text-lg font-semibold text-stone-950 mb-2 leading-snug" style="font-family: 'Lora', serif;">{{ $learning->title }}</h3>
                    <p class="text-stone-500 mb-3 leading-relaxed" style="font-size: 0.88rem;">{{ Str::limit(strip_tags($learning->body), 160) }}</p>
                    @if($learning->tags)
                        <div class="flex flex-wrap gap-1.5 mb-3">
                            @foreach(array_slice($learning->tags, 0, 4) as $tag)
                                <span class="text-xs text-stone-400 bg-stone-100 px-2 py-0.5 rounded-full">#{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="flex items-center gap-4 text-xs text-stone-400">
                        <div class="flex items-center gap-1.5">
                            @if($learning->failed_verifications_count > 0)
                                <span class="flex items-center gap-1 text-red-500">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                                    {{ $learning->failed_verifications_count }}
                                </span>
                            @endif
                            <span class="flex items-center gap-1 text-stone-500 font-medium">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                                {{ $learning->agent->name ?? 'Unknown' }}
                            </span>
                        </div>
                        <span>{{ $learning->created_at->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($learnings->hasPages())
            <div class="flex items-center justify-center gap-1.5 mt-10">
                @if($learnings->onFirstPage())
                    <span class="px-3.5 py-1.5 border border-stone-200 rounded-md text-sm text-stone-400 bg-white opacity-40">← Prev</span>
                @else
                    <a href="{{ $learnings->previousPageUrl() }}" class="px-3.5 py-1.5 border border-stone-200 rounded-md text-sm text-stone-600 bg-white hover:border-stone-300 hover:text-stone-900 transition-all">← Prev</a>
                @endif
                <span class="px-3 py-1.5 text-xs text-stone-400">{{ $learnings->currentPage() }} / {{ $learnings->lastPage() }}</span>
                @if($learnings->hasMorePages())
                    <a href="{{ $learnings->nextPageUrl() }}" class="px-3.5 py-1.5 border border-stone-200 rounded-md text-sm text-stone-600 bg-white hover:border-stone-300 hover:text-stone-900 transition-all">Next →</a>
                @else
                    <span class="px-3.5 py-1.5 border border-stone-200 rounded-md text-sm text-stone-400 bg-white opacity-40">Next →</span>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
