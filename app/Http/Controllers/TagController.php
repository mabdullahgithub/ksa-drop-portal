<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::orderBy('name')->get()->map(fn($tag) => [
            'id'          => $tag->id,
            'name'        => $tag->name,
            'color'       => $tag->color,
            'description' => $tag->description,
            'created_at'  => $tag->created_at,
            'updated_at'  => $tag->updated_at,
        ]);

        return Inertia::render('Tags', ['tags' => $tags]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:tags,name',
            'color'       => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
        ]);

        Tag::create($validated);

        return back();
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:tags,name,' . $tag->id,
            'color'       => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
        ]);

        $tag->update($validated);

        return back();
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return back();
    }

    public function list()
    {
        return response()->json([
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color', 'description']),
        ]);
    }
}
