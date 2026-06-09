<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    /**
     * GET /api/services — Public
     * للزوار العاديين مع Cache
     */
    public function index(Request $request)
    {
        $lang = in_array($request->query('lang'), ['en', 'ar']) ? $request->query('lang') : 'en';

        $services = Cache::remember("services_{$lang}", 300, function () use ($lang) {
            return Service::orderBy('order')->get()->map(function ($s) use ($lang) {
                return [
                    'id'          => (string) $s->id,
                    'title'       => $lang === 'ar' ? $s->title_ar : $s->title_en,
                    'description' => $lang === 'ar' ? $s->description_ar : $s->description_en,
                    'icon'        => $s->icon,
                    'gif'         => $s->gif,
                ];
            });
        });

        return response()->json($services);
    }

    /**
     * GET /api/admin/services — Protected (auth:sanctum)
     * ✅ مسار منفصل ومحمي للأدمن — بيانات ثنائية اللغة
     */
    public function adminIndex()
    {
        $services = Service::orderBy('order')->get()->map(function ($s) {
            return [
                'id'             => (string) $s->id,
                'title_en'       => $s->title_en,
                'title_ar'       => $s->title_ar,
                'description_en' => $s->description_en,
                'description_ar' => $s->description_ar,
                'icon'           => $s->icon,
                'gif'            => $s->gif,
                'order'          => $s->order,
            ];
        });

        return response()->json($services);
    }

    /**
     * POST /api/services — Protected (auth:sanctum)
     * إنشاء خدمة جديدة
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title_en'       => 'required|string|max:255',
            'title_ar'       => 'required|string|max:255',
            'description_en' => 'required|string|max:2000',
            'description_ar' => 'required|string|max:2000',
            'icon'           => 'required|string|max:100',
            'gif'            => 'nullable|string|max:500',
            'order'          => 'nullable|integer|min:0',
        ]);

        $service = Service::create($validated);

        Cache::forget('services_en');
        Cache::forget('services_ar');

        return response()->json($service, 201);
    }

    /**
     * PUT /api/services/{id} — Protected (auth:sanctum)
     * تعديل خدمة
     */
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'title_en'       => 'required|string|max:255',
            'title_ar'       => 'required|string|max:255',
            'description_en' => 'required|string|max:2000',
            'description_ar' => 'required|string|max:2000',
            'icon'           => 'required|string|max:100',
            'gif'            => 'nullable|string|max:500',
            'order'          => 'nullable|integer|min:0',
        ]);

        $service->update($validated);

        Cache::forget('services_en');
        Cache::forget('services_ar');

        return response()->json($service);
    }

    /**
     * DELETE /api/services/{id} — Protected (auth:sanctum)
     * حذف خدمة
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        Cache::forget('services_en');
        Cache::forget('services_ar');

        return response()->json(['deleted' => true]);
    }
}
