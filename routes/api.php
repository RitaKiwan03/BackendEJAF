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
use App\Http\Controllers\Api\TeamController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\RecoveryController;
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
// ✅ CAPTCHA يُولَّد على السيرفر — Rate limited لمنع توليد آلاف الأسئلة
Route::get('/auth/captcha', [AuthController::class, 'captcha'])
    ->middleware('throttle:20,1');

// ✅ Login — 5 محاولات كل دقيقة لمنع brute force
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// ── Public Content ───────────────────────────────────────
Route::get('/services', [ServiceController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/blog', [PostController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/blog/search', [PostController::class, 'search'])
    ->middleware('throttle:30,1');

Route::get('/blog/{slug}', [PostController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1');

// ✅ Contact — 3 رسائل كل 10 دقائق لمنع spam
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:3,10');

Route::get('/locations', [LocationController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/settings', [SettingController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/team', [TeamController::class, 'index'])
    ->middleware('throttle:60,1');

Route::get('/search', [PostController::class, 'search'])
    ->middleware('throttle:30,1');

// ✅ Visitor tracking — 30 requests per minute
Route::post('/visitors/track', [VisitorController::class, 'track'])
    ->middleware('throttle:30,1');

// ============================================================
// PROTECTED ROUTES — auth:sanctum مطلوب
// ============================================================
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

    // ── Auth ──────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // ── User Management (Admin only) ─────────────────────
    Route::get('/admin/users', [AuthController::class, 'getUsers']);
    Route::post('/admin/users/{id}/password', [AuthController::class, 'changeModeratorPassword']);
    Route::post('/admin/users/{id}/block', [AuthController::class, 'blockModerator']);

    // ── Upload ────────────────────────────────────────────
    Route::post('/upload', [UploadController::class, 'upload']);

    // ── Visitors Stats ────────────────────────────────────
    Route::get('/visitors/stats', [VisitorController::class, 'stats']);

    // ── Services ─────────────────────────────────────────
    Route::get('/admin/services', fn() => response()->json(
        \App\Models\Service::orderBy('order')->get()
    ));
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    // ── Projects ────────────────────────────────────────
    Route::get('/admin/projects', fn() => response()->json(
        \App\Models\Project::latest()->get()
    ));
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);

    // ── Blog ─────────────────────────────────────────────
    Route::get('/admin/blog', fn() => response()->json(
        \App\Models\Post::latest('created_at_display')->get()
    ));
    Route::post('/blog', [PostController::class, 'store']);
    Route::put('/blog/{id}', [PostController::class, 'update']);
    Route::delete('/blog/{id}', [PostController::class, 'destroy']);

    // ── Contact Messages ─────────────────────────────────
    Route::get('/contact', [ContactController::class, 'index']);
    Route::put('/contact/{id}/read', [ContactController::class, 'markRead']);
    Route::delete('/contact/{id}', [ContactController::class, 'destroy']);

    // ── Locations ────────────────────────────────────────
    Route::post('/locations', [LocationController::class, 'store']);
    Route::put('/locations/{id}', [LocationController::class, 'update']);
    Route::delete('/locations/{id}', [LocationController::class, 'destroy']);

    // ── Settings ─────────────────────────────────────────
    Route::put('/settings', [SettingController::class, 'update']);
    Route::post('/settings/logo', [SettingController::class, 'uploadLogo']);
    Route::post('/settings/favicon', [SettingController::class, 'uploadFavicon']);
    Route::get('/settings/logo', fn() => response()->json([
        'url' => \App\Models\Setting::where('key', 'logo')->first()?->value,
    ]));

    // ── Team ─────────────────────────────────────────────
    Route::post('/team', [TeamController::class, 'store']);
    Route::put('/team/{id}', [TeamController::class, 'update']);
    Route::delete('/team/{id}', [TeamController::class, 'destroy']);
});
// ============================================================
// RECOVERY ROUTES — سرية تماماً (محمية بـ Recovery Code)
// ============================================================
Route::post('/recovery/verify', [RecoveryController::class, 'verify']);
Route::get('/recovery/users', [RecoveryController::class, 'users']);
Route::post('/recovery/reset-admin', [RecoveryController::class, 'resetAdmin']);
Route::post('/recovery/create-admin', [RecoveryController::class, 'createAdmin']);
Route::post('/recovery/delete-user', [RecoveryController::class, 'deleteUser']);
Route::post('/recovery/force-logout', [RecoveryController::class, 'forceLogout']);
Route::post('/recovery/reset-moderator', [RecoveryController::class, 'resetModeratorPassword'])
    ->middleware('throttle:10,1');
