<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member; // model yang konek ke DB 'rifa'

class LeaderMemberController extends Controller
{
    public function index()
    {
        // Pastikan hanya leader yang bisa akses
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403, 'Unauthorized. Leader access only.');
        }

        $page = "members";
        $members = Member::with('division')->get();

        return view('leaders.members.index', compact('page', 'members'));
    }
}
