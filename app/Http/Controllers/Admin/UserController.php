<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            return redirect()->route('login.form')->withErrors(['loginError' => 'Admin only.']);
        }

        $page = "user";
        $users = User::all();

        return view('admins.users.index', compact('page', 'users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'Username_User' => 'required|unique:users,Username_User|max:20',
            'Name_User'     => 'required|string|max:100',
            'Password_User' => 'required',
            'Id_Type_User'  => 'required|in:1,2',
        ]);

        User::create([
            'Username_User' => $request->Username_User,
            'Name_User'     => $request->Name_User,
            'Password_User' => $request->Password_User,
            'Id_Type_User'  => $request->Id_Type_User,
        ]);

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
            // Password opsional
            'Password_User' => 'nullable|string|min:6',
        ]);

        $data = $request->only(['Username_User', 'Name_User', 'Id_Type_User']);

        // Hanya update password jika diisi
        if ($request->filled('Password_User')) {
            $data['Password_User'] = $request->Password_User; // Laravel otomatis hash karena di model ada casts: 'password' => 'hashed'
        }

        $user->update($data);

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
