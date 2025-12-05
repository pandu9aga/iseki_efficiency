<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member; // model yang konek ke DB 'rifa'

class AdminMemberController extends Controller
{
    public function index()
    {
        // Pastikan hanya admin yang bisa akses
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403, 'Unauthorized');
        }

        $page = "members";
        $members = Member::with('division')->get();

        return view('admins.members.index', compact('page', 'members'));
    }
}
