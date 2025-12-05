<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardMemberController extends Controller
{
    public function index()
    {
        // Cek apakah member sudah login (berdasarkan session Id_Member)
        if (!session()->has('Id_Member')) {
            return redirect()->route('login.form')
                ->withErrors(['loginError' => 'Member access only. Please log in first.']);
        }

        $page = "home";
        $today = Carbon::today();
        $member = [
            'Id_Member' => session('Id_Member'),
            'NIK_Member' => session('NIK_Member'),
            'Name_Member' => session('Name_Member'),
        ];

        return view('members.dashboard', compact('page', 'today', 'member'));
    }
}
