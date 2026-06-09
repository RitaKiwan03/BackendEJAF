<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Support\Facades\Route;

// ============================================
// Public Routes — لا تحتاج توكن
// ============================================

// Auth
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1'); // حماية: 10 محاولات كل دقيقة فقط

// Settings — عام للموقع (قراءة فقط)
Route::get('/settings', [SettingController::class, 'index']);
Route::post('/settings/favicon', [SettingController::class, 'uploadFavicon']);
Route::post('/settings/logo', [SettingController::class, 'uploadLogo']);
Route::get('/locations',             [LocationController::class, 'index']);
// Services — عام للموقع (قراءة فقط، بدون dashboard mode)
Route::get('/services', [ServiceController::class, 'index']);

// Projects — عام للموقع (قراءة فقط، بدون dashboard mode)
Route::get('/projects', [ProjectController::class, 'index']);

// Blog — عام للموقع (قراءة فقط، بدون dashboard mode)
Route::get('/blog',           [PostController::class, 'index']);
Route::get('/blog/search',    [PostController::class, 'search']);
Route::get('/blog/{slug}',    [PostController::class, 'show']);

// Contact — الزوار يرسلون رسائل
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:5,1'); // حماية: 5 رسائل كل دقيقة فقط

// Visitors tracking — عام
Route::post('/visitors/track', [VisitorController::class, 'track'])
    ->middleware('throttle:30,1'); // حماية: 30 طلب كل دقيقة

// ============================================
// Protected Routes — تحتاج توكن أدمن
// ============================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Auth ---
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // --- Upload ---
    Route::post('/upload', [UploadController::class, 'upload']);

    // --- Visitor Stats (أدمن فقط) ---
    Route::get('/visitors/stats', [VisitorController::class, 'stats']);

    // --- Locations ---

    Route::post('/locations',            [LocationController::class, 'store']);
    Route::put('/locations/{id}',        [LocationController::class, 'update']);
    Route::delete('/locations/{id}',     [LocationController::class, 'destroy']);

    // --- Services (أدمن فقط — القراءة مع dashboard عبر query param محمي) ---
    Route::get('/admin/services', function () {
        return response()->json(
            \App\Models\Service::orderBy('order')->get()
        );
    });
    Route::post('/services',             [ServiceController::class, 'store']);
    Route::put('/services/{id}',         [ServiceController::class, 'update']);
    Route::delete('/services/{id}',      [ServiceController::class, 'destroy']);

    // --- Projects (أدمن فقط) ---
    Route::get('/admin/projects', function () {
        return response()->json(
            \App\Models\Project::latest()->get()
        );
    });
    Route::post('/projects',             [ProjectController::class, 'store']);
    Route::put('/projects/{id}',         [ProjectController::class, 'update']);
    Route::delete('/projects/{id}',      [ProjectController::class, 'destroy']);

    // --- Blog (أدمن فقط) ---
    Route::get('/admin/blog', function () {
        return response()->json(
            \App\Models\Post::latest('created_at_display')->get()
        );
    });
    Route::post('/blog',                 [PostController::class, 'store']);
    Route::put('/blog/{id}',             [PostController::class, 'update']);
    Route::delete('/blog/{id}',          [PostController::class, 'destroy']);

    // --- Contact Messages (أدمن فقط) ---
    Route::get('/contact',               [ContactController::class, 'index']);
    Route::put('/contact/{id}/read',     [ContactController::class, 'markRead']);
    Route::delete('/contact/{id}',       [ContactController::class, 'destroy']);

    // --- Settings (أدمن فقط) ---
    Route::put('/settings',              [SettingController::class, 'update']);
    Route::post('/settings/logo',        [SettingController::class, 'uploadLogo']);
});
