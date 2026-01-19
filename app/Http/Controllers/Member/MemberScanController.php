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
        if (!session()->has('Id_Member')) {
            return redirect()->route('login.form')->with('error', 'Silakan login terlebih dahulu.');
        }

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
            'Id_Tractor' => 'required|integer',
            'Name_Tractor' => 'required|string',
            'Nik_Original_Replaced' => 'nullable|string|size:16', // harus 16 karakter jika diisi
        ]);

        $tractor = Tractor::where('Id_Tractor', $request->Id_Tractor)->first();

        if (!$tractor) {
            return back()->with('error', 'Tractor tidak ditemukan.');
        }

        $memberId = session('Id_Member');
        if (!$memberId) {
            return back()->with('error', 'Session member tidak valid.');
        }

        $nikReplaced = $request->filled('Nik_Original_Replaced')
            ? trim($request->Nik_Original_Replaced)
            : null;

        // Sesuaikan Id_Area dan Id_Daily_Job jika diperlukan
        Scan::create([
            'Id_Member' => $memberId,
            'Id_Tractor' => $tractor->Id_Tractor,
            'Id_Area' => null,               // ← ganti jika punya logika area
            'Id_Daily_Job' => null,          // ← ganti jika punya logika daily job
            'Time_Scan' => Carbon::now(),
            'Assigned_Hour_Scan' => $tractor->Hour_Tractor,
            'Nik_Original_Replaced' => $nikReplaced,
        ]);

        return back()->with('success', 'Scan berhasil disimpan.');
    }
}
