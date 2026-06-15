<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryController extends Controller
{
    /**
     * POST /api/recovery/verify
     * التحقق من Recovery Code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'recovery_code' => 'required|string',
        ]);

        $valid = hash_equals(
            env('RECOVERY_CODE', ''),
            $request->recovery_code
        );

        if (!$valid) {
            return response()->json([
                'message' => 'كود الاسترجاع غير صحيح',
            ], 403);
        }

        // ✅ إنشاء توكن مؤقت صالح 10 دقائق
        $tempToken = Str::random(64);
        cache()->put('recovery_token_' . $tempToken, true, now()->addMinutes(10));

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

        $users = User::select('id', 'name', 'username', 'role', 'is_admin', 'created_at')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * POST /api/recovery/reset-admin
     * إعادة تعيين كلمة مرور الأدمن
     */
    public function resetAdmin(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'username' => 'required|string',
            'new_password' => 'required|string|min:8',
        ]);

        $user = User::where('username', $request->username)
            ->where('is_admin', true)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود'], 404);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        $user->tokens()->delete();

        return response()->json(['message' => 'تم إعادة تعيين كلمة المرور بنجاح']);
    }

    /**
     * POST /api/recovery/create-admin
     * إنشاء أدمن جديد
     */
    public function createAdmin(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|regex:/^[a-z0-9_]+$/|max:100|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'is_admin' => true,
            'role' => 'admin',
        ]);

        return response()->json([
            'message' => 'تم إنشاء الأدمن بنجاح',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
            ],
        ]);
    }

    /**
     * POST /api/recovery/delete-user
     * حذف مستخدم مشبوه
     */
    public function deleteUser(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        // ✅ منع حذف آخر أدمن
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return response()->json([
                'message' => 'لا يمكن حذف آخر أدمن في النظام',
            ], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم']);
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

        \Laravel\Sanctum\PersonalAccessToken::query()->delete();

        return response()->json([
            'message' => 'تم تسجيل خروج جميع المستخدمين',
        ]);
    }

    private function isValidRecoveryToken(Request $request): bool
    {
        $token = $request->header('X-Recovery-Token');
        if (!$token) return false;

        return cache()->has('recovery_token_' . $token);
    }

    /**
     * POST /api/recovery/reset-moderator
     * إعادة تعيين كلمة مرور الـ moderator (يتطلب كلمة مرور الأدمن)
     */
    public function resetModeratorPassword(Request $request)
    {
        if (!$this->isValidRecoveryToken($request)) {
            return response()->json(['message' => 'غير مصرح'], 401);
        }

        $request->validate([
            'admin_password'     => 'required|string',
            'moderator_username' => 'required|string',
            'new_password'       => 'required|string|min:8',
        ]);

        // ✅ التحقق من كلمة مرور الأدمن
        $admin = User::where('role', 'admin')->first();
        if (!$admin || !Hash::check($request->admin_password, $admin->password)) {
            return response()->json([
                'message' => 'كلمة مرور الأدمن غير صحيحة'
            ], 401);
        }

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
}
