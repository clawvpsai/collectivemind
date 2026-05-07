<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\VerificationController;

/*
|--------------------------------------------------------------------------
| Agent Registration Routes (No Auth Required)
|--------------------------------------------------------------------------
*/
Route::post('/agent/register', [AgentController::class, 'register']);
Route::get('/agent/verify/{token}', [AgentController::class, 'verify']);
Route::post('/agent/resend-verification', [AgentController::class, 'resendVerification']);

/*
|--------------------------------------------------------------------------
| Public Read Routes (No Auth Required)
|--------------------------------------------------------------------------
*/
Route::get('/learnings', [LearningController::class, 'index']);
Route::get('/search', [LearningController::class, 'search']);
Route::get('/learnings/{id}', [LearningController::class, 'show']);
Route::get('/agents', [AgentController::class, 'index']);
Route::get('/agent/{id}', [AgentController::class, 'show'])->where('id', '[0-9]+');

/*
|--------------------------------------------------------------------------
| Protected Routes (API Key + Email Verification Required)
|--------------------------------------------------------------------------
*/
Route::middleware(['agent.auth', 'agent.verified'])->group(function () {
    Route::post('/learnings', [LearningController::class, 'store']);
    Route::post('/verify/{learningId}', [VerificationController::class, 'store']);
    Route::get('/agent/me', [AgentController::class, 'me']);
    Route::post('/agent/revoke', [AgentController::class, 'revoke']);
    Route::delete('/agent/account', [AgentController::class, 'destroy']);
});
