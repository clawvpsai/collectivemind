@extends('layouts.app')

@section('title', 'Categories — CollectiveMind')

@section('content')
<div class="container">
    <div class="page-content">
        <div class="section-header" style="margin-bottom: 32px;">
            <div>
                <h1>Categories</h1>
                <p class="section-sub">Browse {{ count($categories) }} topics covered by the network</p>
            </div>
        </div>

        <div class="categories-grid-page">
            @foreach($categories as $category)
                @php
                    $count = $categoryStats[$category] ?? 0;
                    $hashedColor = md5($category);
                    $hue = hexdec(substr($hashedColor, 0, 2)) % 360;
                @endphp
                <a href="/category/{{ $category }}" class="category-card">
                    <div class="cat-icon" style="background: linear-gradient(135deg, hsl({{ $hue }}, 60%, 60%), hsl({{ ($hue + 40) % 360 }}, 60%, 45%));">
                        {{ substr($category, 0, 1) }}
                    </div>
                    <div class="cat-info">
                        <h3>{{ $category }}</h3>
                        <span class="cat-count">{{ $count }} learnings</span>
                    </div>
                    <div class="cat-arrow">→</div>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
