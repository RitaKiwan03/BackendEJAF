<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TeamMember::orderBy('row')->orderBy('order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_en' => 'required|string|max:100',
            'name_ar' => 'required|string|max:100',
            'role_en' => 'required|string|max:100',
            'role_ar' => 'required|string|max:100',
            'image'   => 'nullable|string',
            'row'     => 'required|integer|min:1|max:4',
            'order'   => 'required|integer|min:0',
        ]);
        $member = TeamMember::create($data);
        return response()->json($member, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $member = TeamMember::findOrFail($id);
        $data = $request->validate([
            'name_en' => 'sometimes|string|max:100',
            'name_ar' => 'sometimes|string|max:100',
            'role_en' => 'sometimes|string|max:100',
            'role_ar' => 'sometimes|string|max:100',
            'image'   => 'nullable|string',
            'row'     => 'sometimes|integer|min:1|max:4',
            'order'   => 'sometimes|integer|min:0',
        ]);
        $member->update($data);
        return response()->json($member);
    }

    public function destroy(string $id): JsonResponse
    {
        TeamMember::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
