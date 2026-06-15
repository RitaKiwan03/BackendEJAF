<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class RecoveryController extends Controller
{
    /**
     * POST /api/recovery/verify
     * التحقق بكلمة مرور الأدمن
     */
    public function verify(Request $request)
    {
        $request->validate([
            'admin_password' => 'required|string',
        ]);

        // ✅ البحث عن الأدمن
        $admin = User::where('role', 'admin')->first();

        if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
            return response()->json([
                'message' => 'كلمة مرور الأدمن غير صحيحة'
            ], 401);
        }

        // ✅ إنشاء توكن مؤقت صالح 10 دقائق
        $tempToken = Str::random(64);
        cache()->put(
            'recovery_token_' . $tempToken,
            true,
            now()->addMinutes(10)
        );

        return response()->json([
            'message' => 'تم التحقق بنجاح',
            'temp_token' => $tempToken,
        ]);
    }

    /**
     * GET /api/recovery/users
     * عرض جميع المستخدمين
     */
    public function users(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        // ✅ إضافة is_blocked و role
        $users = User::select('id', 'name', 'username', 'role', 'is_admin', 'is_blocked', 'created_at')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * POST /api/recovery/reset-moderator
     * إعادة تعيين كلمة مرور الـ moderator
     * (تم التحقق من الأدمن مسبقاً في verify)
     */
    public function resetModeratorPassword(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'moderator_username' => 'required|string',
            'new_password'       => 'required|string|min:8',
        ]);

        // ✅ البحث عن الـ moderator
        $moderator = User::where('username', $request->moderator_username)
            ->where('role', 'moderator')
            ->first();

        if (!$moderator) {
            return response()->json([
                'message' => 'المشرف غير موجود'
            ], 404);
        }

        $moderator->update([
            'password' => Hash::make($request->new_password)
        ]);
        $moderator->tokens()->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة مرور المشرف بنجاح'
        ]);
    }

    /**
     * POST /api/recovery/block-user
     * حظر حقيقي للمستخدم (is_blocked = true)
     */
    public function blockUser(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        // ✅ منع حظر الأدمن
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'لا يمكن حظر الأدمن'
            ], 422);
        }

        // ✅ حظر حقيقي + حذف الجلسات النشطة
        $user->update(['is_blocked' => true]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم حظر المستخدم بنجاح'
        ]);
    }

    /**
     * POST /api/recovery/unblock-user
     * فك الحظر عن المستخدم (is_blocked = false)
     */
    public function unblockUser(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        // ✅ منع تعديل حالة الأدمن
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'لا يمكن تعديل حالة الأدمن'
            ], 422);
        }

        // ✅ فك الحظر
        $user->update(['is_blocked' => false]);

        return response()->json([
            'message' => 'تم فك الحظر بنجاح'
        ]);
    }

    /**
     * POST /api/recovery/force-logout
     * إجبار جميع المستخدمين على تسجيل الخروج
     */
    public function forceLogout(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $count = PersonalAccessToken::query()->delete();

        return response()->json([
            'message' => "تم تسجيل خروج جميع المستخدمين ({$count} جلسة)"
        ]);
    }

    /**
     * التحقق من صحة التوكن المؤقت
     */
    private function isValidRecoveryToken(Request $request): bool
    {
        $token = $request->header('X-Recovery-Token');
        if (!$token) return false;

        return cache()->has('recovery_token_' . $token);
    }
}
