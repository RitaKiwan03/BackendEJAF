<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /**
     * GET /api/blog — Public
     * للزوار العاديين فقط، مع دعم اللغة
     */
    public function index(Request $request)
    {
        $lang = in_array($request->query('lang'), ['en', 'ar']) ? $request->query('lang') : 'en';

        $posts = Post::latest('created_at_display')
            ->get()
            ->map(function ($p) use ($lang) {
                $tagsData    = $p->tags;
                $selectedTags = '';

                if (is_array($tagsData)) {
                    $selectedTags = $lang === 'ar' ? ($tagsData['ar'] ?? '') : ($tagsData['en'] ?? '');
                } elseif (is_string($tagsData)) {
                    $selectedTags = $tagsData;
                }

                return [
                    'id'        => (string) $p->id,
                    'title'     => $lang === 'ar' ? $p->title_ar : $p->title_en,
                    'excerpt'   => $lang === 'ar' ? $p->excerpt_ar : $p->excerpt_en,
                    'slug'      => $p->slug,
                    'image'     => $p->image,
                    'tags'      => $selectedTags,
                    'createdAt' => $p->created_at_display,
                    // ✅ content غير مُرسَل هنا لتحسين الأداء — يُجلب عبر show()
                ];
            });

        return response()->json($posts);
    }

    /**
     * GET /api/blog/{slug} — Public
     * تفاصيل مقال واحد للزائر
     */
    public function show(Request $request, string $slug)
    {
        $lang = in_array($request->query('lang'), ['en', 'ar']) ? $request->query('lang') : 'en';

        $post = Post::where('slug', $slug)->firstOrFail();

        $tagsData     = $post->tags;
        $selectedTags = '';
        if (is_array($tagsData)) {
            $selectedTags = $lang === 'ar' ? ($tagsData['ar'] ?? '') : ($tagsData['en'] ?? '');
        } elseif (is_string($tagsData)) {
            $selectedTags = $tagsData;
        }

        return response()->json([
            'id'        => (string) $post->id,
            'title'     => $lang === 'ar' ? $post->title_ar : $post->title_en,
            'excerpt'   => $lang === 'ar' ? $post->excerpt_ar : $post->excerpt_en,
            'content'   => $lang === 'ar' ? $post->content_ar : $post->content_en,
            'slug'      => $post->slug,
            'image'     => $post->image,
            'tags'      => $selectedTags,
            'createdAt' => $post->created_at_display,
        ]);
    }

    /**
     * GET /api/admin/blog — Protected (auth:sanctum)
     * ✅ مسار منفصل ومحمي للأدمن — لا ثغرة dashboard=true
     */
    public function adminIndex()
    {
        $posts = Post::latest('created_at_display')
            ->get()
            ->map(function ($p) {
                return [
                    'id'                 => (string) $p->id,
                    'title_en'           => $p->title_en,
                    'title_ar'           => $p->title_ar,
                    'excerpt_en'         => $p->excerpt_en,
                    'excerpt_ar'         => $p->excerpt_ar,
                    'content_en'         => $p->content_en,
                    'content_ar'         => $p->content_ar,
                    'slug'               => $p->slug,
                    'image'              => $p->image,
                    'tags'               => $p->tags,
                    'createdAt'          => $p->created_at_display,
                ];
            });

        return response()->json($posts);
    }

    /**
     * POST /api/blog — Protected (auth:sanctum)
     * إنشاء مقال جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title_en'           => 'required|string|max:255',
            'title_ar'           => 'required|string|max:255',
            'excerpt_en'         => 'required|string|max:1000',
            'excerpt_ar'         => 'required|string|max:1000',
            'content_en'         => 'required|string',
            'content_ar'         => 'required|string',
            'slug'               => 'required|string|unique:posts,slug|max:255|regex:/^[a-z0-9\-]+$/',
            'image'              => 'nullable|string|max:500',
            'tags'               => 'required|array',
            'tags.en'            => 'required|string|max:500',
            'tags.ar'            => 'required|string|max:500',
            'created_at_display' => 'required|date',
        ]);

        $post = Post::create($validated);

        return response()->json([
            'id'        => (string) $post->id,
            'slug'      => $post->slug,
            'createdAt' => $post->created_at_display,
        ], 201);
    }

    /**
     * PUT /api/blog/{id} — Protected (auth:sanctum)
     * تعديل مقال
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title_en'           => 'required|string|max:255',
            'title_ar'           => 'required|string|max:255',
            'excerpt_en'         => 'required|string|max:1000',
            'excerpt_ar'         => 'required|string|max:1000',
            'content_en'         => 'required|string',
            'content_ar'         => 'required|string',
            'slug'               => 'required|string|unique:posts,slug,' . $id . '|max:255|regex:/^[a-z0-9\-]+$/',
            'image'              => 'nullable|string|max:500',
            'tags'               => 'required|array',
            'tags.en'            => 'required|string|max:500',
            'tags.ar'            => 'required|string|max:500',
            'created_at_display' => 'required|date',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    /**
     * DELETE /api/blog/{id} — Protected (auth:sanctum)
     * حذف مقال
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * GET /api/blog/search — Public
     * البحث في المقالات
     */
    public function search(Request $request)
    {
        $lang  = in_array($request->query('lang'), ['en', 'ar']) ? $request->query('lang') : 'en';
        $query = $request->query('q', '');

        // ✅ منع البحث الفارغ
        if (empty(trim($query))) {
            return response()->json([]);
        }

        $posts = Post::where(function ($q) use ($query) {
            $q->where('title_en',   'LIKE', "%{$query}%")
                ->orWhere('title_ar',   'LIKE', "%{$query}%")
                ->orWhere('excerpt_en', 'LIKE', "%{$query}%")
                ->orWhere('excerpt_ar', 'LIKE', "%{$query}%");
            // ✅ حذف البحث في content لأداء أفضل
        })
            ->latest('created_at_display')
            ->get()
            ->map(function ($p) use ($lang) {
                return [
                    'id'        => (string) $p->id,
                    'title'     => $lang === 'ar' ? $p->title_ar : $p->title_en,
                    'excerpt'   => $lang === 'ar' ? $p->excerpt_ar : $p->excerpt_en,
                    'slug'      => $p->slug,
                    'image'     => $p->image,
                    'createdAt' => $p->created_at_display,
                ];
            });

        return response()->json($posts);
    }
}
