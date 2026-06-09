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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ============================================================
// Health Check — للتأكد أن السيرفر يعمل
// ============================================================
Route::get('/health', function () {
    return response()->json([
        'status'  => 'ok',
        'time'    => now()->toISOString(),
        'version' => app()->version(),
    ]);
});

// ============================================================
// PUBLIC ROUTES — لا تحتاج توكن
// ============================================================

// ── Auth ──────────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1'); // 5 محاولات كل دقيقة

// ── Services ──────────────────────────────────────────────
Route::get('/services', [ServiceController::class, 'index']);

// ── Projects ──────────────────────────────────────────────
Route::get('/projects', [ProjectController::class, 'index']);

// ── Blog ──────────────────────────────────────────────────
Route::get('/blog',        [PostController::class, 'index']);
Route::get('/blog/search', [PostController::class, 'search']);
// ملاحظة: {slug} يجب أن يكون بعد /search لتجنب التعارض
Route::get('/blog/{slug}', [PostController::class, 'show'])
    ->where('slug', '[a-z0-9-]+');

// ── Contact ───────────────────────────────────────────────
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:3,10'); // 3 رسائل كل 10 دقائق

// ── Locations (public read) ───────────────────────────────
Route::get('/locations', [LocationController::class, 'index']);

// ── Settings (public read) ────────────────────────────────
Route::get('/settings', [SettingController::class, 'index']);

// ── Settings logo — public GET للشعار ────────────────────
Route::get('/settings/logo', function () {
    try {
        $setting = \App\Models\Setting::where('key', 'logo')->first();
        return response()->json([
            'url' => $setting?->value ?? null,
        ]);
    } catch (\Exception $e) {
        return response()->json(['url' => null]);
    }
});

// ── Visitors tracking — public ────────────────────────────
Route::post('/visitors/track', [VisitorController::class, 'track'])
    ->middleware('throttle:30,1');

// ── Search — public ───────────────────────────────────────
Route::get('/search', [PostController::class, 'search']);

Route::get('/admin/projects', function () {
    return response()->json(
        \App\Models\Project::latest()->get()
    );
});

Route::get('/admin/blog', function () {
    return response()->json(
        \App\Models\Post::latest('created_at_display')->get()
    );
});
Route::get('/admin/services', function () {
    return response()->json(
        \App\Models\Service::orderBy('order')->get()
    );
});

// ============================================================
// PROTECTED ROUTES — تحتاج توكن أدمن (auth:sanctum)
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth ──────────────────────────────────────────────
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // ── Upload ────────────────────────────────────────────
    Route::post('/upload', [UploadController::class, 'upload']);

    // ── Visitors Stats ────────────────────────────────────
    Route::get('/visitors/stats', [VisitorController::class, 'stats']);

    // ── Services (Admin CRUD) ─────────────────────────────
    // جلب كل الحقول للداشبورد

    Route::post('/services',         [ServiceController::class, 'store']);
    Route::put('/services/{id}',     [ServiceController::class, 'update']);
    Route::delete('/services/{id}',  [ServiceController::class, 'destroy']);

    // ── Projects (Admin CRUD) ─────────────────────────────

    Route::post('/projects',         [ProjectController::class, 'store']);
    Route::put('/projects/{id}',     [ProjectController::class, 'update']);
    Route::delete('/projects/{id}',  [ProjectController::class, 'destroy']);

    // ── Blog (Admin CRUD) ─────────────────────────────────

    Route::post('/blog',             [PostController::class, 'store']);
    Route::put('/blog/{id}',         [PostController::class, 'update']);
    Route::delete('/blog/{id}',      [PostController::class, 'destroy']);

    // ── Contact Messages (Admin) ──────────────────────────
    Route::get('/contact',              [ContactController::class, 'index']);
    Route::put('/contact/{id}/read',    [ContactController::class, 'markRead']);
    Route::delete('/contact/{id}',      [ContactController::class, 'destroy']);

    // ── Locations (Admin CRUD) ────────────────────────────
    Route::post('/locations',           [LocationController::class, 'store']);
    Route::put('/locations/{id}',       [LocationController::class, 'update']);
    Route::delete('/locations/{id}',    [LocationController::class, 'destroy']);

    // ── Settings (Admin) ──────────────────────────────────
    Route::put('/settings',             [SettingController::class, 'update']);
    Route::post('/settings/logo',       [SettingController::class, 'uploadLogo']);
    Route::post('/settings/favicon',    [SettingController::class, 'uploadFavicon']);
});
