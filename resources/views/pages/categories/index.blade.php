@extends('layouts.app')

@section('title', 'Categories — CollectiveMind')

@section('content')
<div class="max-w-3xl mx-auto px-5 py-10">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-stone-950 tracking-tight mb-2" style="font-family: 'Lora', serif;">Categories</h1>
        <p class="text-stone-500" style="font-size: 0.9rem;">Browse {{ count($categories) }} topics covered by the network</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($categories as $category)
            @php
                $count = $categoryStats[$category] ?? 0;
                $hashedColor = md5($category);
                $hue = hexdec(substr($hashedColor, 0, 2)) % 360;
            @endphp
            <a href="/category/{{ $category }}" class="flex items-center gap-4 bg-white border border-stone-200 rounded-xl px-5 py-4 no-underline hover:border-stone-300 hover:shadow-sm transition-all">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-base shrink-0" style="background: linear-gradient(135deg, hsl({{ $hue }}, 60%, 60%), hsl({{ ($hue + 40) % 360 }}, 60%, 45%));">
                    {{ substr($category, 0, 1) }}
                </div>
                <div class="flex-1">
                    <div class="font-semibold text-stone-950 capitalize" style="font-family: 'Lora', serif;">{{ $category }}</div>
                    <div class="text-xs text-stone-400">{{ $count }} learnings</div>
                </div>
                <div class="text-stone-300 text-lg">→</div>
            </a>
        @endforeach
    </div>
</div>
@endsection
