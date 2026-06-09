<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use enshrined\svgSanitize\Sanitizer;

class UploadController extends Controller
{
    /**
     * الامتدادات المسموحة
     */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /**
     * MIME Types المسموحة
     */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * POST /api/upload — Protected (auth:sanctum)
     * رفع الصور مع حماية كاملة لجميع الصيغ
     */
    public function upload(Request $request)
    {
        $file = $request->file('file');

        if (!$file) {
            return response()->json(['error' => 'لم يتم إرسال ملف'], 422);
        }

        // ✅ 1. التحقق من الامتداد
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return response()->json([
                'error' => 'نوع الملف غير مسموح. الأنواع المسموحة: '
                    . implode(', ', self::ALLOWED_EXTENSIONS)
            ], 422);
        }

        // ✅ 2. التحقق من MIME الحقيقي
        $realMime = $file->getMimeType();

        if (!in_array($realMime, self::ALLOWED_MIMES)) {
            return response()->json([
                'error' => 'نوع الملف الحقيقي غير مسموح'
            ], 422);
        }

        // ✅ 3. التحقق من الحجم (5MB كحد أقصى)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'error' => 'حجم الملف كبير جداً (الحد الأقصى 5MB)'
            ], 422);
        }

        // ✅ 4. اسم عشوائي آمن
        $randomName = Str::uuid() . '.' . $extension;

        // ✅ 5. معالجة SVG بشكل خاص — تعقيم كامل
        if ($extension === 'svg') {
            return $this->handleSvg($file, $randomName);
        }

        // ✅ 6. باقي الصور (jpg, png, gif, webp) — حفظ مباشر
        $path = $file->storeAs('uploads', $randomName, 'public');

        return response()->json([
            'url'  => '/storage/' . $path,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * معالجة SVG بأمان كامل باستخدام مكتبة enshrined/svg-sanitize
     */
    private function handleSvg($file, string $randomName)
    {
        $svgContent = file_get_contents($file->getRealPath());

        if ($svgContent === false) {
            return response()->json(['error' => 'فشل قراءة الملف'], 422);
        }

        // ✅ تعقيم SVG — يحذف script و event handlers و javascript: تلقائياً
        $sanitizer = new Sanitizer();
        $cleanSvg  = $sanitizer->sanitize($svgContent);

        if ($cleanSvg === false || empty(trim($cleanSvg))) {
            return response()->json([
                'error' => 'الملف يحتوي على محتوى غير مسموح'
            ], 422);
        }

        // ✅ حفظ الـ SVG المنظف
        $path = 'uploads/' . $randomName;
        Storage::disk('public')->put($path, $cleanSvg);

        return response()->json([
            'url'  => '/storage/' . $path,
            'name' => $randomName,
            'size' => strlen($cleanSvg),
        ]);
    }
}
