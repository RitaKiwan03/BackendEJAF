<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * GET /api/locations — Protected (auth:sanctum)
     */
    public function index(): JsonResponse
    {
        $locations = Cache::remember('locations', 3600, function () {
            return Location::latest()->get();
        });

        return response()->json($locations);
    }

    /**
     * POST /api/locations — Protected (auth:sanctum)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eyebrow_en'    => 'required|string|max:255',
            'eyebrow_ar'    => 'required|string|max:255',
            'location_name' => 'required|string|max:255',
            'title_en'      => 'required|string|max:255',
            'title_ar'      => 'required|string|max:255',
            'desc_en'       => 'required|string|max:3000',
            'desc_ar'       => 'required|string|max:3000',
            'map_url'       => 'required|url|max:500',
            'lat'           => 'required|numeric|between:-90,90',
            'lng'           => 'required|numeric|between:-180,180',
        ]);

        $location = Location::create($validated);

        $this->clearCache();

        return response()->json($location, 201);
    }

    /**
     * PUT /api/locations/{id} — Protected (auth:sanctum)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'eyebrow_en'    => 'required|string|max:255',
            'eyebrow_ar'    => 'required|string|max:255',
            'location_name' => 'required|string|max:255',
            'title_en'      => 'required|string|max:255',
            'title_ar'      => 'required|string|max:255',
            'desc_en'       => 'required|string|max:3000',
            'desc_ar'       => 'required|string|max:3000',
            'map_url'       => 'required|url|max:500',
            'lat'           => 'required|numeric|between:-90,90',
            'lng'           => 'required|numeric|between:-180,180',
        ]);

        $location->update($validated);

        $this->clearCache();

        return response()->json($location);
    }

    /**
     * DELETE /api/locations/{id} — Protected (auth:sanctum)
     */
    public function destroy($id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $location->delete();

        $this->clearCache();

        return response()->json(['deleted' => true]);
    }

    /**
     * تنظيف Cache عند أي تغيير
     */
    private function clearCache(): void
    {
        Cache::forget('locations');
    }
}
