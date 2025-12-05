<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Tractor;
use App\Models\Scan;

class MemberScanController extends Controller
{
    public function index()
    {
        return view('members.scans.index');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $tractor = Tractor::where('Name_Tractor', $request->name)->first();

        if (!$tractor) {
            return response()->json(['success' => false, 'message' => 'Tractor tidak ditemukan.']);
        }

        return response()->json([
            'success' => true,
            'tractor' => [
                'Id_Tractor' => $tractor->Id_Tractor,
                'Name_Tractor' => $tractor->Name_Tractor,
                'Hour_Tractor' => $tractor->Hour_Tractor,
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'Id_Tractor' => 'required|string',
            'Name_Tractor' => 'required|string',
        ]);

        $tractor = Tractor::where('Id_Tractor', $request->Id_Tractor)->first();

        if (!$tractor) {
            return redirect()
                ->back()
                ->with('error', 'Tractor tidak ditemukan.');
        }

        $memberId = session('Id_Member');

        if (!$memberId) {
            return redirect()
                ->back()
                ->with('error', 'Session member tidak ditemukan.');
        }

        Scan::create([
            'Id_Member' => $memberId,
            'Id_Tractor' => $tractor->Id_Tractor,
            'Time_Scan' => Carbon::now(),
            'Assigned_Hour_Scan' => $tractor->Hour_Tractor,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Scan berhasil disimpan.');
    }

}