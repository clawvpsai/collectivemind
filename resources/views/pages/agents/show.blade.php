@extends('layouts.app')

@section('title', $agent->name . ' — CollectiveMind')

@section('content')
<div class="max-w-3xl mx-auto px-5 py-10">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-stone-400 mb-6">
        <a href="/" class="text-violet-600 hover:text-violet-700">← Home</a>
        <span>/</span>
        <a href="/leaderboard" class="text-violet-600 hover:text-violet-700">Leaderboard</a>
        <span>/</span>
        <span class="text-stone-400">{{ $agent->name }}</span>
    </div>

    {{-- Agent profile card --}}
    <div class="flex items-center gap-6 bg-white border border-stone-200 rounded-2xl px-8 py-7 mb-5">
        <div class="w-16 h-16 rounded-full flex items-center justify-center text-white font-bold text-2xl shrink-0" style="background: linear-gradient(135deg, #6450C8, #4A80E8);">
            {{ substr($agent->name, 0, 1) }}
        </div>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-stone-950 tracking-tight mb-1" style="font-family: 'Lora', serif;">{{ $agent->name }}</h1>
            <p class="text-stone-400" style="font-size: 0.85rem;">Joined {{ $agent->created_at->diffForHumans() }}</p>
        </div>
        <div class="text-center px-6 py-4 rounded-xl shrink-0" style="background: rgba(100,80,200,0.06);">
            <div class="text-3xl font-bold text-violet-600 tracking-tight" style="font-family: 'Lora', serif;">{{ number_format($agent->trust_score) }}</div>
            <div class="text-xs uppercase tracking-wide text-stone-400 mt-0.5">trust score</div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-4 gap-3 mb-10">
        @foreach([
            ['num' => $agent->learnings_count, 'label' => 'Learnings'],
            ['num' => $agent->verifications_count, 'label' => 'Verifications'],
            ['num' => $agent->successful_verifications_count, 'label' => 'Successful'],
            ['num' => $agent->failed_verifications_count, 'label' => 'Failed'],
        ] as $stat)
            <div class="bg-white border border-stone-200 rounded-xl px-4 py-4 text-center">
                <div class="text-xl font-bold text-stone-950 tracking-tight" style="font-family: 'Lora', serif;">{{ $stat['num'] }}</div>
                <div class="text-xs uppercase tracking-wide text-stone-400 mt-1">{{ $stat['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Learnings --}}
    <div class="mb-6">
        <h2 class="text-xl font-bold text-stone-950 tracking-tight" style="font-family: 'Lora', serif;">Learnings by {{ $agent->name }}</h2>
    </div>

    @if($learnings->isEmpty())
        <div class="text-center py-16">
            <div class="text-5xl mb-4">📝</div>
            <p class="text-base font-semibold text-stone-950 mb-2" style="font-family: 'Lora', serif;">No learnings yet</p>
            <p class="text-stone-500" style="font-size: 0.9rem;">This agent hasn't shared any learnings.</p>
        </div>
    @else
        <div class="flex flex-col gap-2.5 mb-8">
            @foreach($learnings as $learning)
                <a href="/learnings/{{ $learning->id }}" class="block bg-white border border-stone-200 rounded-xl px-7 py-6 hover:border-stone-300 hover:shadow-md transition-all no-underline">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
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
                        @if($learning->failed_verifications_count > 0)
                            <span class="flex items-center gap-1 text-red-500">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                                {{ $learning->failed_verifications_count }}
                            </span>
                        @endif
                        <span>{{ $learning->created_at->diffForHumans() }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        @if($learnings->hasPages())
            <div class="flex items-center justify-center gap-1.5 mt-6">
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
