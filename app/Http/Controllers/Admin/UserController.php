<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::all();

        return response()->json(['users' => $users]);
    }

     public function store(Request $request)
    {
        $admin = $request->user();

        if (!in_array($admin->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'username' => 'nullable|string|unique:users',
            'phone_number' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'role' => 'required|in:super_admin,admin,client,photographer,editor',
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Generate a temporary password or leave it blank
        $validated['password'] = Hash::make('defaultpassword'); // or generate one

        $user = User::create($validated);

        return response()->json(['message' => 'User created successfully.', 'user' => $user], 201);
    }

    public function getClients()
    {
        $clients = User::where('role', 'client')->get();

        return response()->json([
            'status' => 'success',
            'data' => $clients
        ]);
    }

    public function getPhotographers()
    {
        $photographers = User::where('role', 'photographer')->get();

        return response()->json([
            'status' => 'success',
            'data' => $photographers
        ]);
    }

    // Lightweight public list (id + name + email) for UI dropdowns
    public function simplePhotographers()
    {
        $photographers = User::where('role', 'photographer')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $photographers
        ]);
    }

    /**
     * Admin-only: update a user's primary role
     */
    public function updateRole(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || !in_array($admin->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:super_admin,admin,client,photographer,editor',
        ]);

        $user = User::findOrFail($id);
        $oldRole = $user->role;
        $user->role = $validated['role'];
        $changed = $user->isDirty('role');
        $user->save();

        return response()->json([
            'message' => $changed ? 'Role updated successfully.' : 'Role unchanged.',
            'changed' => $changed,
            'user' => $user,
            'old_role' => $oldRole,
            'new_role' => $user->role,
        ]);
    }
}
