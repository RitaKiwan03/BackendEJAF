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
     * ✅ يرجع الرسالة المناسبة حسب لغة الطلب (Accept-Language header)
     * الافتراضي عربي إذا لم يُرسَل الـ header أو كانت قيمته غير en
     */
    private function msg(Request $request, string $ar, string $en): string
    {
        $lang = strtolower((string) $request->header('Accept-Language', 'ar'));
        return str_starts_with($lang, 'en') ? $en : $ar;
    }

    /**
     * GET /api/auth/captcha — Public
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

        Cache::put("captcha:{$id}", $answer, now()->addMinutes(self::CAPTCHA_TTL_MINUTES));

        return response()->json([
            'captcha_id' => $id,
            'question'   => "{$a} {$op} {$b}",
        ]);
    }

    /**
     * POST /api/auth/login — Public
     */
    public function login(Request $request)
    {
        $request->validate([
            'username'       => 'required|string|max:100',
            'password'       => 'required|string|min:8|max:255',
            'captcha_id'     => 'required|string',
            'captcha_answer' => 'required|numeric',
        ]);

        $expected = Cache::pull("captcha:{$request->captcha_id}");

        if ($expected === null || (int) $request->captcha_answer !== (int) $expected) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'فشل التحقق الأمني، حاول مرة أخرى',
                    'Security verification failed, please try again'
                )
            ], 422);
        }

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'بيانات الدخول غير صحيحة',
                    'Invalid login credentials'
                )
            ], 401);
        }

        if (!$user->is_admin) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'غير مصرح لك بالدخول',
                    'You are not authorized to access this area'
                )
            ], 403);
        }

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
                'message' => $this->msg(
                    $request,
                    'كلمة المرور الحالية غير صحيحة',
                    'Current password is incorrect'
                )
            ], 401);
        }

        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'كلمة المرور الجديدة يجب أن تكون مختلفة عن الحالية',
                    'New password must be different from the current one'
                )
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        $user->tokens()->delete();

        return response()->json([
            'message' => $this->msg(
                $request,
                'تم تغيير كلمة المرور بنجاح، يرجى تسجيل الدخول مجدداً',
                'Password changed successfully, please log in again'
            )
        ]);
    }

    /**
     * POST /api/auth/logout — Protected (auth:sanctum)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => $this->msg($request, 'تم تسجيل الخروج بنجاح', 'Logged out successfully')
        ]);
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
