<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    private const CAPTCHA_TTL_MINUTES = 5;

    /**
     * GET /api/auth/captcha — Public
     * ✅ يولّد سؤال CAPTCHA على السيرفر ويخزن الإجابة في Cache
     *    (الإجابة الصحيحة لا تُرسَل للمتصفح أبداً)
     */
    public function captcha(Request $request)
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);

        $ops = ['+', '-', '*'];
        $op  = $ops[array_rand($ops)];

        $answer = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
        };

        $id = (string) Str::uuid();

        // ✅ تُحفظ الإجابة 5 دقائق فقط ويُستخدم المعرّف مرة واحدة
        Cache::put("captcha:{$id}", $answer, now()->addMinutes(self::CAPTCHA_TTL_MINUTES));

        return response()->json([
            'captcha_id' => $id,
            'question'   => "{$a} {$op} {$b}",
        ]);
    }

    /**
     * POST /api/auth/login — Public
     * تسجيل الدخول للأدمن فقط — مع تحقق CAPTCHA من السيرفر
     */
    public function login(Request $request)
    {
        $request->validate([
            'username'       => 'required|string|max:100',
            'password'       => 'required|string|min:8|max:255',
            'captcha_id'     => 'required|string',
            'captcha_answer' => 'required|numeric',
        ]);

        // ✅ التحقق من CAPTCHA على السيرفر — Cache::pull يحذفها فوراً (one-time use)
        $expected = Cache::pull("captcha:{$request->captcha_id}");

        if ($expected === null || (int) $request->captcha_answer !== (int) $expected) {
            return response()->json([
                'message' => 'فشل التحقق الأمني، حاول مرة أخرى'
            ], 422);
        }

        $user = User::where('username', $request->username)->first();

        // ✅ رسالة موحدة لمنع تخمين اسم المستخدم
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة'
            ], 401);
        }

        if (!$user->is_admin) {
            return response()->json([
                'message' => 'غير مصرح لك بالدخول'
            ], 403);
        }

        // ✅ حذف التوكنات القديمة قبل إنشاء توكن جديد (منع تراكم التوكنات)
        $user->tokens()->delete();

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'is_admin' => $user->is_admin,
            ]
        ]);
    }

    /**
     * POST /api/auth/change-password — Protected (auth:sanctum)
     * تغيير كلمة المرور للأدمن
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|max:255|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 401);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الجديدة يجب أن تكون مختلفة عن الحالية'
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        // ✅ إلغاء جميع التوكنات بعد تغيير الباسورد (إجبار على إعادة الدخول)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح، يرجى تسجيل الدخول مجدداً'
        ]);
    }

    /**
     * POST /api/auth/logout — Protected (auth:sanctum)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * GET /api/auth/me — Protected (auth:sanctum)
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'is_admin' => $user->is_admin,
        ]);
    }
}
