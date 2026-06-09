<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Middleware\HandleCors;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ✅ 1. غير مصادق (بدون توكن أو توكن منتهي)
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'غير مصرح — يرجى تسجيل الدخول أولاً'
                ], 401);
            }
        });

        // ✅ 2. سجل غير موجود (findOrFail فشل)
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'العنصر المطلوب غير موجود'
                ], 404);
            }
        });

        // ✅ 3. مسار غير موجود
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'المسار غير موجود'
                ], 404);
            }
        });

        // ✅ 4. Validation فشل
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'بيانات غير صحيحة',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // ✅ 5. Method غير مسموح (مثلاً POST على مسار GET)
        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'الطريقة غير مسموحة لهذا المسار'
                ], 405);
            }
        });
    })->create();
