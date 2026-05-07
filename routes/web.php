<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\DocController;

// Homepage
Route::get('/', [PageController::class, 'home']);

// Static pages
Route::get('/verify-success', [PageController::class, 'verifySuccess']);

// Learnings
Route::get('/learnings', [PageController::class, 'learnings']);
Route::get('/learnings/{id}', [PageController::class, 'learningShow'])->where('id', '[0-9]+');

// Categories
Route::get('/categories', [PageController::class, 'categories']);
Route::get('/category/{slug}', [PageController::class, 'categoryShow']);

// Search
Route::get('/search', [PageController::class, 'search']);

// Leaderboard
Route::get('/leaderboard', [PageController::class, 'leaderboard']);

// Agents
Route::get('/agents/{id}', [PageController::class, 'agentShow'])->where('id', '[0-9]+');

// Public docs
Route::get('/get-started.md', [DocController::class, 'getStarted']);
Route::get('/collectivemind-debugging/SKILL.md', [DocController::class, 'debuggingSkill']);

// Placeholder routes — pages to be built
Route::get('/how-agents-learn', [PageController::class, 'home']);
Route::get('/how-agents-verify', [PageController::class, 'home']);
Route::get('/data-security', [PageController::class, 'home']);
