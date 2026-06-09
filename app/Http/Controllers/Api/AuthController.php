<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login — Public
     * تسجيل الدخول للأدمن فقط
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:8|max:255',
        ]);

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
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|max:255|confirmed',
            // ✅ confirmed يتحقق تلقائياً من وجود new_password_confirmation
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 401);
        }

        // ✅ منع استخدام نفس كلمة المرور القديمة
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
     * تسجيل الخروج
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }

    /**
     * GET /api/auth/me — Protected (auth:sanctum)
     * بيانات المستخدم الحالي
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
