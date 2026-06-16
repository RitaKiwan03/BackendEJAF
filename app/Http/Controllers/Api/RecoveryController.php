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
     */
    public function verify(Request $request)
    {
        $request->validate(['admin_password' => 'required|string']);

        $admin = User::where('role', 'admin')->first();

        if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
            return response()->json(['message' => 'كلمة مرور الأدمن غير صحيحة'], 401);
        }

        $tempToken = Str::random(64);
        cache()->put('recovery_token_' . $tempToken, true, now()->addMinutes(10));

        return response()->json([
            'message'    => 'تم التحقق بنجاح',
            'temp_token' => $tempToken,
        ]);
    }

    /**
     * GET /api/recovery/users
     * ✅ يستخدم is_blocked الموجود فعلاً في DB
     */
    public function users(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $users = User::select('id', 'name', 'username', 'role', 'is_admin', 'is_blocked', 'created_at')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * POST /api/recovery/reset-moderator
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

        $moderator = User::where('username', $request->moderator_username)
            ->where('role', 'moderator')
            ->first();

        if (!$moderator) {
            return response()->json(['message' => 'المشرف غير موجود'], 404);
        }

        $moderator->update(['password' => Hash::make($request->new_password)]);
        $moderator->tokens()->delete();

        return response()->json(['message' => 'تم تغيير كلمة مرور المشرف بنجاح']);
    }

    /**
     * POST /api/recovery/block-user
     * ✅ is_blocked = true + مسح التوكنات
     * ✅ AuthController يتحقق من is_blocked عند login → يمنع الدخول
     */
    public function blockUser(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $user = User::find($request->user_id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'لا يمكن حظر الأدمن'], 422);
        }

        // ✅ الحظر الحقيقي
        $user->update(['is_blocked' => true]);
        $user->tokens()->delete();

        return response()->json([
            'message'    => 'تم حظر المستخدم بنجاح',
            'is_blocked' => true,
        ]);
    }

    /**
     * POST /api/recovery/unblock-user
     * ✅ is_blocked = false
     */
    public function unblockUser(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate(['user_id' => 'required|integer|exists:users,id']);

        $user = User::find($request->user_id);

        if ($user->role === 'admin') {
            return response()->json(['message' => 'لا يمكن تعديل حالة الأدمن'], 422);
        }

        $user->update(['is_blocked' => false]);

        return response()->json([
            'message'    => 'تم فك الحظر بنجاح',
            'is_blocked' => false,
        ]);
    }

    /**
     * POST /api/recovery/force-logout
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

    private function isValidRecoveryToken(Request $request): bool
    {
        $token = $request->header('X-Recovery-Token');
        if (!$token) return false;
        return cache()->has('recovery_token_' . $token);
    }
}
