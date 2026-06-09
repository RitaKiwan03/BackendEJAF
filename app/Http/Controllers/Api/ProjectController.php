<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProjectController extends Controller
{
    /**
     * GET /api/projects — Public
     */
    public function index(Request $request)
    {
        $lang = in_array($request->query('lang'), ['en', 'ar']) ? $request->query('lang') : 'en';

        $projects = Cache::remember("projects_{$lang}", 600, function () use ($lang) {
            return Project::latest()->get()->map(function ($p) use ($lang) {
                return [
                    'id'           => (string) $p->id,
                    'title'        => $lang === 'ar' ? $p->title_ar : $p->title_en,
                    'description'  => $lang === 'ar' ? $p->description_ar : $p->description_en,
                    'image'        => $p->image,
                    'technologies' => $lang === 'ar'
                        ? ($p->technologies['ar'] ?? [])
                        : ($p->technologies['en'] ?? []),
                ];
            });
        });

        return response()->json($projects);
    }

    /**
     * GET /api/admin/projects — Protected (auth:sanctum)
     */
    public function adminIndex()
    {
        $projects = Project::latest()->get()->map(function ($p) {
            return [
                'id'             => (string) $p->id,
                'title_en'       => $p->title_en,
                'title_ar'       => $p->title_ar,
                'description_en' => $p->description_en,
                'description_ar' => $p->description_ar,
                'image'          => $p->image,
                'technologies'   => $p->technologies,
            ];
        });

        return response()->json($projects);
    }

    /**
     * POST /api/projects — Protected (auth:sanctum)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title_en'        => 'required|string|max:255',
            'title_ar'        => 'required|string|max:255',
            'description_en'  => 'required|string|max:2000',
            'description_ar'  => 'required|string|max:2000',
            'image'           => 'nullable|string|max:500',
            'technologies'    => 'required|array',
            'technologies.en' => 'required|array',
            'technologies.ar' => 'required|array',
        ]);

        $project = Project::create($validated);

        $this->clearCache();

        return response()->json([
            'id'    => (string) $project->id,
            'title' => $project->title_en,
        ], 201);
    }

    /**
     * PUT /api/projects/{id} — Protected (auth:sanctum)
     */
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $validated = $request->validate([
            'title_en'        => 'required|string|max:255',
            'title_ar'        => 'required|string|max:255',
            'description_en'  => 'required|string|max:2000',
            'description_ar'  => 'required|string|max:2000',
            'image'           => 'nullable|string|max:500',
            'technologies'    => 'required|array',
            'technologies.en' => 'required|array',
            'technologies.ar' => 'required|array',
        ]);

        $project->update($validated);

        $this->clearCache();

        return response()->json($project);
    }

    /**
     * DELETE /api/projects/{id} — Protected (auth:sanctum)
     */
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        $this->clearCache();

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * تنظيف Cache عند أي تغيير
     */
    private function clearCache(): void
    {
        Cache::forget('projects_en');
        Cache::forget('projects_ar');
    }
}
