<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use enshrined\svgSanitize\Sanitizer;
use Cloudinary\Cloudinary;

class SettingController extends Controller
{
    private const PUBLIC_KEYS = [
        'phone',
        'email',
        'logo_url',
        'favicon_url',
        'facebook',
        'instagram',
        'twitter',
        'linkedin',
        'whatsapp',
        'youtube',
        'tiktok',
    ];
    private const CACHE_KEY     = 'public_settings';
    private const CACHE_SECONDS = 3600;

    public function index(): JsonResponse
    {
        $settings = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            return Setting::whereIn('key', self::PUBLIC_KEYS)
                ->get()
                ->pluck('value', 'key');
        });

        return response()->json($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|string|email|max:255',
            'facebook'  => 'nullable|url|max:255',
            'instagram' => 'nullable|url|max:255',
            'twitter'   => 'nullable|url|max:255',
            'linkedin'  => 'nullable|url|max:255',
            'whatsapp'  => 'nullable|string|max:255',
            'youtube'   => 'nullable|url|max:255',
            'tiktok'    => 'nullable|url|max:255',
        ]);

        foreach ($validated as $key => $value) {
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        $this->clearCache();
        return response()->json(['message' => 'تم تحديث الإعدادات بنجاح']);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => 'required|file|max:5120']);

        $file      = $request->file('logo');
        $extension = strtolower($file->getClientOriginalExtension());
        $realMime  = $file->getMimeType();

        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        $allowedMimes      = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];

        if (!in_array($extension, $allowedExtensions)) {
            return response()->json(['error' => 'صيغة غير مسموحة'], 422);
        }

        if (!in_array($realMime, $allowedMimes)) {
            return response()->json(['error' => 'نوع الملف غير مسموح'], 422);
        }

        $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));

        if ($extension === 'svg') {
            $svgContent = file_get_contents($file->getRealPath());
            $sanitizer  = new Sanitizer();
            $cleanSvg   = $sanitizer->sanitize($svgContent);

            if (!$cleanSvg) {
                return response()->json(['error' => 'ملف SVG غير آمن'], 422);
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'svg_') . '.svg';
            file_put_contents($tmpPath, $cleanSvg);
            $result = $cloudinary->uploadApi()->upload($tmpPath, ['folder' => 'ejaf/logo', 'resource_type' => 'image']);
            unlink($tmpPath);
            $url = $result['secure_url'];
        } else {
            $result = $cloudinary->uploadApi()->upload($file->getRealPath(), ['folder' => 'ejaf/logo']);
            $url    = $result['secure_url'];
        }

        Setting::set('logo_url', $url);

        $faviconUrl = null;
        if ($extension !== 'gif') {
            Setting::set('favicon_url', $url);
            $faviconUrl = $url;
        }

        $this->clearCache();

        return response()->json(['url' => $url, 'favicon_url' => $faviconUrl]);
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate(['favicon' => 'required|file|max:1024']);

        $file      = $request->file('favicon');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'svg', 'ico'])) {
            return response()->json(['error' => 'صيغة غير مسموحة للـ favicon'], 422);
        }

        $cloudinary = new Cloudinary(config('cloudinary.cloud_url'));
        $result     = $cloudinary->uploadApi()->upload($file->getRealPath(), ['folder' => 'ejaf/favicon']);
        $url        = $result['secure_url'];

        Setting::set('favicon_url', $url);
        $this->clearCache();

        return response()->json(['url' => $url]);
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
