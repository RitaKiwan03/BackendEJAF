<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use enshrined\svgSanitize\Sanitizer;

class SettingController extends Controller
{
    private const PUBLIC_KEYS   = ['phone', 'email', 'logo_url', 'favicon_url'];
    private const CACHE_KEY     = 'public_settings';
    private const CACHE_SECONDS = 3600;

    // GET /api/settings — Public
    public function index(): JsonResponse
    {
        $settings = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return Setting::whereIn('key', self::PUBLIC_KEYS)
                ->get()
                ->pluck('value', 'key');
        });

        return response()->json($settings);
    }

    // PUT /api/settings — Protected
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:255',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        $this->clearCache();
        return response()->json(['message' => 'تم تحديث الإعدادات بنجاح']);
    }

    // POST /api/settings/logo — Protected
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => 'required|file|max:5120']);

        $file      = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension());
        $realMime  = $file->getMimeType();

        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        $allowedMimes      = [
            'image/png',
            'image/jpeg',
            'image/webp',
            'image/gif',
            'image/svg+xml',
        ];

        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'error' => 'صيغة الملف غير مسموحة. المسموح: ' . implode(', ', $allowedExtensions)
            ], 422);
        }

        if (!in_array($realMime, $allowedMimes)) {
            return response()->json(['error' => 'نوع الملف الحقيقي غير مسموح'], 422);
        }

        $randomName = Str::uuid() . '.' . $extension;

        // ✅ SVG — تعقيم كامل
        if ($extension === 'svg') {
            $svgContent = file_get_contents($file->getRealPath());

            if ($svgContent === false) {
                return response()->json(['error' => 'فشل قراءة الملف'], 422);
            }

            $sanitizer = new Sanitizer();
            $cleanSvg  = $sanitizer->sanitize($svgContent);

            if ($cleanSvg === false || empty(trim($cleanSvg))) {
                return response()->json(['error' => 'الملف يحتوي على محتوى غير مسموح'], 422);
            }

            $path = 'logo/' . $randomName;
            Storage::disk('public')->put($path, $cleanSvg);
            $url = '/storage/' . $path;

            Setting::set('logo_url', $url);

            // ✅ SVG يمكن استخدامه كـ favicon
            Setting::set('favicon_url', $url);

            $this->clearCache();
            return response()->json(['url' => $url, 'favicon_url' => $url]);
        }

        // ✅ باقي الصيغ
        $path = $file->storeAs('logo', $randomName, 'public');
        $url  = '/storage/' . $path;

        Setting::set('logo_url', $url);

        // ✅ إذا ليس GIF - احفظ نسخة كـ favicon
        $faviconUrl = null;
        if ($extension !== 'gif') {
            $faviconName = Str::uuid() . '.' . $extension;
            $faviconPath = $file->storeAs('favicon', $faviconName, 'public');
            $faviconUrl  = '/storage/' . $faviconPath;
            Setting::set('favicon_url', $faviconUrl);
        }

        $this->clearCache();

        return response()->json([
            'url'         => $url,
            'favicon_url' => $faviconUrl,
        ]);
    }

    // POST /api/settings/favicon — Protected
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => 'required|file|max:1024',
        ]);

        $file      = $request->file('favicon');
        $extension = strtolower($file->getClientOriginalExtension());
        $realMime  = $file->getMimeType();

        // ✅ favicon لا يدعم GIF في المتصفحات
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'svg', 'ico'];
        $allowedMimes      = [
            'image/png',
            'image/jpeg',
            'image/svg+xml',
            'image/x-icon',
            'image/vnd.microsoft.icon',
        ];

        if (!in_array($extension, $allowedExtensions)) {
            return response()->json([
                'error' => 'صيغة غير مسموحة للـ favicon. المسموح: ' . implode(', ', $allowedExtensions)
            ], 422);
        }

        if (!in_array($realMime, $allowedMimes)) {
            return response()->json(['error' => 'نوع الملف الحقيقي غير مسموح'], 422);
        }

        $randomName = Str::uuid() . '.' . $extension;

        // ✅ SVG - تعقيم
        if ($extension === 'svg') {
            $svgContent = file_get_contents($file->getRealPath());
            if ($svgContent === false) {
                return response()->json(['error' => 'فشل قراءة الملف'], 422);
            }
            $sanitizer = new Sanitizer();
            $cleanSvg  = $sanitizer->sanitize($svgContent);
            if ($cleanSvg === false || empty(trim($cleanSvg))) {
                return response()->json(['error' => 'الملف يحتوي على محتوى غير مسموح'], 422);
            }
            $path = 'favicon/' . $randomName;
            Storage::disk('public')->put($path, $cleanSvg);
            $url = '/storage/' . $path;
        } else {
            $path = $file->storeAs('favicon', $randomName, 'public');
            $url  = '/storage/' . $path;
        }

        Setting::set('favicon_url', $url);
        $this->clearCache();

        return response()->json(['url' => $url]);
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
