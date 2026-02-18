<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Area;

class UserController extends Controller
{
    public function index()
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            return redirect()->route('login.form')->withErrors(['loginError' => 'Admin only.']);
        }

        $page = "user";
        // Eager load areas (many-to-many) and legacy area
        $users = User::with(['area', 'areas'])->get();
        $areas = Area::all();

        return view('admins.users.index', compact('page', 'users', 'areas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'Username_User' => 'required|unique:users,Username_User|max:20',
            'Name_User'     => 'required|string|max:100',
            'Password_User' => 'required',
            'Id_Type_User'  => 'required|in:1,2',
            // 'Id_Area'    => 'nullable', // Deprecated but might be passed
            'area_ids'      => 'nullable|array',
            'area_ids.*'    => 'exists:areas,Id_Area',
        ]);

        $user = User::create([
            'Username_User' => $request->Username_User,
            'Name_User'     => $request->Name_User,
            'Password_User' => $request->Password_User,
            'Id_Type_User'  => $request->Id_Type_User,
            // 'Id_Area' => null, // We stop assigning this for new logic, or we could assign first one for legacy support?
            // Let's keep it null for now to force usage of new logic, OR take first one if needed.
            'Id_Area'       => $request->input('area_ids.0'), // Optional backward compat
        ]);

        if ($request->has('area_ids')) {
            $user->areas()->sync($request->area_ids);
        }

        return redirect()->route('admins.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'Username_User' => 'required|max:20|unique:users,Username_User,' . $id . ',Id_User',
            'Name_User'     => 'required|string|max:100',
            'Id_Type_User'  => 'required|in:1,2',
            'area_ids'      => 'nullable|array',
            'area_ids.*'    => 'exists:areas,Id_Area',
            'Password_User' => 'nullable|string|min:6',
        ]);

        $data = $request->only(['Username_User', 'Name_User', 'Id_Type_User']);

        if ($request->filled('Password_User')) {
            $data['Password_User'] = $request->Password_User;
        }

        // Backward compat: update Id_Area to first selected area
        $data['Id_Area'] = $request->input('area_ids.0');

        $user->update($data);

        if ($request->has('area_ids')) {
            $user->areas()->sync($request->area_ids);
        }

        return redirect()->route('admins.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy($id)
    {
        if ($id == session('Id_User')) {
            return back()->withErrors(['error' => 'Tidak bisa menghapus diri sendiri.']);
        }

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admins.users.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
