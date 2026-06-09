<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use enshrined\svgSanitize\Sanitizer;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UploadController extends Controller
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    public function upload(Request $request)
    {
        $file = $request->file('file');

        if (!$file) {
            return response()->json(['error' => 'لم يتم إرسال ملف'], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return response()->json([
                'error' => 'نوع الملف غير مسموح. الأنواع المسموحة: '
                    . implode(', ', self::ALLOWED_EXTENSIONS)
            ], 422);
        }

        $realMime = $file->getMimeType();

        if (!in_array($realMime, self::ALLOWED_MIMES)) {
            return response()->json(['error' => 'نوع الملف الحقيقي غير مسموح'], 422);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'حجم الملف كبير جداً (الحد الأقصى 5MB)'], 422);
        }

        // ✅ SVG - تعقيم ثم رفع على Cloudinary
        if ($extension === 'svg') {
            return $this->handleSvg($file);
        }

        // ✅ باقي الصور - رفع مباشر على Cloudinary
        try {
            $result = Cloudinary::upload($file->getRealPath(), [
                'folder' => 'ejaf/uploads',
                'public_id' => Str::uuid(),
            ]);

            return response()->json([
                'url'  => $result->getSecurePath(),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'فشل رفع الصورة: ' . $e->getMessage()], 500);
        }
    }

    private function handleSvg($file)
    {
        $svgContent = file_get_contents($file->getRealPath());

        if ($svgContent === false) {
            return response()->json(['error' => 'فشل قراءة الملف'], 422);
        }

        $sanitizer = new Sanitizer();
        $cleanSvg  = $sanitizer->sanitize($svgContent);

        if ($cleanSvg === false || empty(trim($cleanSvg))) {
            return response()->json(['error' => 'الملف يحتوي على محتوى غير مسموح'], 422);
        }

        // ✅ حفظ SVG مؤقتاً ثم رفعه على Cloudinary
        $tmpPath = tempnam(sys_get_temp_dir(), 'svg_') . '.svg';
        file_put_contents($tmpPath, $cleanSvg);

        try {
            $result = Cloudinary::upload($tmpPath, [
                'folder'     => 'ejaf/uploads',
                'public_id'  => Str::uuid(),
                'resource_type' => 'image',
            ]);

            unlink($tmpPath);

            return response()->json([
                'url'  => $result->getSecurePath(),
                'name' => 'image.svg',
                'size' => strlen($cleanSvg),
            ]);
        } catch (\Exception $e) {
            unlink($tmpPath);
            return response()->json(['error' => 'فشل رفع SVG: ' . $e->getMessage()], 500);
        }
    }
}
