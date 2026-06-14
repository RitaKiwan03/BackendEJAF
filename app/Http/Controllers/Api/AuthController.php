<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_MINUTES = 15;
    private const CAPTCHA_TTL = 300; // 5 دقائق

    /**
     * GET /api/auth/captcha — Public
     * يُنشئ CAPTCHA ويحفظ الإجابة في Cache
     */
    public function getCaptcha(): JsonResponse
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $ops = ['+', '-', '*'];
        $op = $ops[array_rand($ops)];

        // تجنب الأرقام السالبة في الطرح
        if ($op === '-' && $a < $b) {
            [$a, $b] = [$b, $a];
        }

        $question = "$a $op $b";

        // حساب الإجابة
        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
        };

        // إنشاء ID فريد للـ CAPTCHA
        $captchaId = Str::random(32);

        // حفظ الإجابة في Cache لمدة 5 دقائق
        Cache::put(
            'captcha_' . $captchaId,
            $answer,
            now()->addSeconds(self::CAPTCHA_TTL)
        );

        return response()->json([
            'captcha_id' => $captchaId,
            'question'   => $question,
        ])->withHeaders($this->securityHeaders());
    }

    /**
     * POST /api/auth/login — Public
     */
    public function login(Request $request)
    {
        $request->validate([
            'username'       => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'password'       => 'required|string|min:8|max:255',
            'captcha_id'     => 'required|string|size:32',
            'captcha_answer' => 'required|integer',
        ]);

        $ip  = $request->ip();
        $key = 'login_attempts_' . $ip;

        // ✅ Rate Limiting أولاً (قبل CAPTCHA)
        $attempts = Cache::get($key, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Log::warning("Login blocked for IP: {$ip}");
            return response()->json([
                'message' => 'تم تجاوز عدد المحاولات المسموحة. حاول بعد ' . self::DECAY_MINUTES . ' دقيقة.',
            ], 429)->withHeaders($this->securityHeaders());
        }

        // ✅ ثم CAPTCHA verification
        $captchaKey = 'captcha_' . $request->captcha_id;
        $correctAnswer = Cache::get($captchaKey);

        if ($correctAnswer === null) {
            return response()->json([
                'message' => 'انتهت صلاحية التحقق، يرجى المحاولة مرة أخرى',
            ], 422)->withHeaders($this->securityHeaders());
        }

        if ((int) $request->captcha_answer !== $correctAnswer) {
            Cache::forget($captchaKey);
            // ✅ زيادة عداد المحاولات عند فشل CAPTCHA
            Cache::put($key, $attempts + 1, now()->addMinutes(self::DECAY_MINUTES));
            return response()->json([
                'message' => 'إجابة التحقق غير صحيحة',
            ], 422)->withHeaders($this->securityHeaders());
        }

        // حذف الـ CAPTCHA بعد الاستخدام (one-time use)
        Cache::forget($captchaKey);

        $user = User::where('username', $request->username)->first();

        // ✅ رسالة موحدة لمنع تخمين اسم المستخدم
        if (!$user || !Hash::check($request->password, $user->password)) {
            Cache::put($key, $attempts + 1, now()->addMinutes(self::DECAY_MINUTES));
            Log::warning("Failed login attempt for IP: {$ip}");
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401)->withHeaders($this->securityHeaders());
        }

        if (!$user->is_admin) {
            return response()->json([
                'message' => 'غير مصرح لك بالدخول',
            ], 403)->withHeaders($this->securityHeaders());
        }

        // ✅ إعادة تعيين عداد المحاولات عند النجاح
        Cache::forget($key);

        // ✅ حذف التوكنات القديمة
        $user->tokens()->delete();
        $token = $user->createToken('admin-token', ['*'], now()->addDay())->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'is_admin' => $user->is_admin,
            ],
        ])->withHeaders($this->securityHeaders());
    }

    /**
     * POST /api/auth/change-password — Protected
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة'], 401)
                ->withHeaders($this->securityHeaders());
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json(['message' => 'كلمة المرور الجديدة يجب أن تكون مختلفة'], 422)
                ->withHeaders($this->securityHeaders());
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح'])
            ->withHeaders($this->securityHeaders());
    }

    /**
     * POST /api/auth/logout — Protected
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح'])
            ->withHeaders($this->securityHeaders());
    }

    /**
     * GET /api/auth/me — Protected
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'is_admin' => $user->is_admin,
        ])->withHeaders($this->securityHeaders());
    }

    // ✅ Security Headers مركزية
    private function securityHeaders(): array
    {
        return [
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => 'DENY',
            'X-XSS-Protection'          => '1; mode=block',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];
    }
}
