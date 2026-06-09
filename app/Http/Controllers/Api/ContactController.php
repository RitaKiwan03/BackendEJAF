<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * POST /api/contact — Public
     * إرسال رسالة من الزائر
     * Rate limiting مضبوط في api.php (throttle:5,1)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'phone_code' => 'nullable|string|max:10',
            'phone'      => 'nullable|string|max:20',
            'subject'    => 'required|string|max:255',
            'message'    => 'required|string|min:10|max:5000', // ✅ min و max للرسالة
        ]);

        ContactMessage::create($validated);

        return response()->json([
            'message' => 'تم إرسال رسالتك بنجاح'
        ], 201);
    }

    /**
     * GET /api/contact — Protected (auth:sanctum)
     * قراءة جميع الرسائل للأدمن
     */
    public function index()
    {
        $messages = ContactMessage::latest()->get();

        return response()->json($messages);
    }

    /**
     * DELETE /api/contact/{id} — Protected (auth:sanctum)
     * حذف رسالة
     */
    public function destroy($id)
    {
        $msg = ContactMessage::findOrFail($id);
        $msg->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * PUT /api/contact/{id}/read — Protected (auth:sanctum)
     * تحديد الرسالة كمقروءة
     */
    public function markRead($id)
    {
        $msg = ContactMessage::findOrFail($id);

        // ✅ تحديث فقط إذا لم تكن مقروءة مسبقاً
        if (!$msg->read_at) {
            $msg->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'تم التحديد كمقروء']);
    }
}
