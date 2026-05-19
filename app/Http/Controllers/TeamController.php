<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::with('members')->get()->map(function ($team) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members_count' => $team->members->count(),
                'members' => $team->members->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ];
        });

        $users = User::select('id', 'name', 'email')->get();

        return Inertia::render('TeamManagement/Teams', [
            'teams' => $teams,
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'members' => 'array',
            'members.*' => 'integer|exists:users,id',
        ]);

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['members'])) {
            $team->members()->sync($validated['members']);
        }

        return back();
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'members' => 'array',
            'members.*' => 'integer|exists:users,id',
        ]);

        $team->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['members'])) {
            $team->members()->sync($validated['members']);
        }

        return back();
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return back();
    }
}
