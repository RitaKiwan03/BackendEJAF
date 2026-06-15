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

    private function msg(Request $request, string $ar, string $en): string
    {
        $lang = strtolower((string) $request->header('Accept-Language', 'ar'));
        return str_starts_with($lang, 'en') ? $en : $ar;
    }

    // GET /api/auth/captcha — Public
    public function captcha(Request $request)
    {
        $a  = random_int(1, 9);
        $b  = random_int(1, 9);
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

    // POST /api/auth/login — Public
    public function login(Request $request)
    {
        $request->validate([
            'username'       => 'required|string|max:100',
            'password'       => 'required|string|min:8|max:255',
            'captcha_id'     => 'required|string',
            'captcha_answer' => 'required|numeric',
        ]);

        // ✅ التحقق من CAPTCHA أولاً
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

        // ✅ التحقق من المستخدم
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

        // ✅ فقط admin و moderator يستطيعان الدخول
        if (!in_array($user->role, ['admin', 'moderator'])) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'غير مصرح لك بالدخول',
                    'You are not authorized to access this area'
                )
            ], 403);
        }
        // ✅ التحقق من الحظر
        if ($user->is_blocked) {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'هذا الحساب محظور، تواصل مع الأدمن',
                    'This account is blocked, contact admin'
                )
            ], 403);
        }

        // ✅ احذف التوكنات القديمة وأنشئ جديدة
        $user->tokens()->delete();
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'role'     => $user->role,
                'is_admin' => $user->role === 'admin',
            ]
        ]);
    }

    // POST /api/auth/logout — Protected
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => $this->msg($request, 'تم تسجيل الخروج بنجاح', 'Logged out successfully')
        ]);
    }

    // GET /api/auth/me — Protected
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'role'     => $user->role,
            'is_admin' => $user->role === 'admin',
        ]);
    }

    // POST /api/auth/change-password — Protected (Admin only)
    public function changePassword(Request $request)
    {
        // ✅ فقط الأدمن يستطيع تغيير كلمة مروره
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => $this->msg(
                    $request,
                    'المشرف لا يستطيع تغيير كلمة المرور',
                    'Moderator cannot change password'
                )
            ], 403);
        }

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
                    'كلمة المرور الجديدة يجب أن تكون مختلفة',
                    'New password must be different'
                )
            ], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete();

        return response()->json([
            'message' => $this->msg(
                $request,
                'تم تغيير كلمة المرور بنجاح',
                'Password changed successfully'
            )
        ]);
    }

    // GET /api/admin/users — Admin only
    public function getUsers(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $users = User::select('id', 'name', 'username', 'role', 'created_at')->get();

        return response()->json(['users' => $users]);
    }

    // POST /api/admin/users/{id}/password — Admin only
    public function changeModeratorPassword(Request $request, $id)
    {
        // ✅ فقط الأدمن
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'هذه العملية للأدمن فقط'], 403);
        }

        $request->validate([
            'admin_password'          => 'required|string',
            'new_password'            => 'required|string|min:8|max:255',
            'new_password_confirmation' => 'required|string|same:new_password',
        ]);

        // ✅ تحقق من كلمة مرور الأدمن
        if (!Hash::check($request->admin_password, $request->user()->password)) {
            return response()->json(['message' => 'كلمة مرور الأدمن غير صحيحة'], 401);
        }

        $moderator = User::findOrFail($id);

        // ✅ لا يمكن تغيير كلمة مرور أدمن آخر
        if ($moderator->role === 'admin') {
            return response()->json(['message' => 'لا يمكن تغيير كلمة مرور الأدمن من هنا'], 422);
        }

        $moderator->update(['password' => Hash::make($request->new_password)]);
        $moderator->tokens()->delete(); // ✅ أجبره على تسجيل الدخول من جديد

        return response()->json(['message' => 'تم تغيير كلمة مرور المشرف بنجاح']);
    }

    // POST /api/admin/users/{id}/block — Admin only
    // POST /api/admin/users/{id}/block — Admin only
    public function blockModerator(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $moderator = User::findOrFail($id);

        if ($moderator->role === 'admin') {
            return response()->json(['message' => 'لا يمكن حظر الأدمن'], 422);
        }

        // ✅ حظر حقيقي + حذف الجلسات النشطة
        $moderator->update(['is_blocked' => true]);
        $moderator->tokens()->delete();

        return response()->json([
            'message' => $this->msg(
                $request,
                'تم حظر المشرف بنجاح',
                'Moderator blocked successfully'
            )
        ]);
    }

    // POST /api/admin/users/{id}/unblock — Admin only
    public function unblockModerator(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $moderator = User::findOrFail($id);

        if ($moderator->role === 'admin') {
            return response()->json(['message' => 'لا يمكن تعديل حالة الأدمن'], 422);
        }

        // ✅ فك الحظر
        $moderator->update(['is_blocked' => false]);

        return response()->json([
            'message' => $this->msg(
                $request,
                'تم فك الحظر بنجاح',
                'Moderator unblocked successfully'
            )
        ]);
    }

   
}
