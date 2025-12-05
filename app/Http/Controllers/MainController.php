<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Member;

class MainController extends Controller
{
    public function index()
    {
        // Cek login user (admin / leader)
        if (session()->has('Id_User')) {
            $type = session('Id_Type_User');
            if ($type == 1) {
                return redirect()->route('admins.dashboard');
            } elseif ($type == 2) {
                return redirect()->route('leaders.dashboard');
            } else {
                session()->flush();
                return redirect()->route('login.form')->withErrors(['loginError' => 'Akses ditolak.']);
            }
        }

        // Cek login member (via NIK)
        if (session()->has('Id_Member')) {
            return redirect()->route('members.home');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'Username_User' => 'required',
            'Password_User' => 'required'
        ]);

        $user = User::where('Username_User', $request->Username_User)->first();

        if (!$user || $request->Password_User !== $user->Password_User) {
            return back()->withErrors(['loginError' => 'Username atau password salah.']);
        }

        session([
            'Id_User' => $user->Id_User,
            'Id_Type_User' => $user->Id_Type_User,
            'Username_User' => $user->Username_User
        ]);

        if ($user->Id_Type_User == 1) {
            return redirect()->route('admins.dashboard');
        } elseif ($user->Id_Type_User == 2) {
            return redirect()->route('leaders.dashboard');
        }

        session()->flush();
        return redirect()->route('login.form')->withErrors(['loginError' => 'Role tidak dikenali.']);
    }

    public function login_member(Request $request)
    {
        $request->validate(['NIK_Member' => 'required']);

        $member = Member::where('nik', $request->NIK_Member)->first();

        if (!$member) {
            return back()->withErrors(['loginError' => 'NIK tidak valid.']);
        }

        session([
            'Id_Member' => $member->id,
            'NIK_Member' => $member->nik,
            'Name_Member' => $member->nama
        ]);

        return redirect()->route('members.home');
    }

    public function logout()
    {
        session()->flush();
        return redirect()->route('login.form');
    }

    public function logout_member()
    {
        session()->flush();
        return redirect()->route('login.form');
    }
}
