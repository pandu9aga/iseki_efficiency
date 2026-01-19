<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Scan;
use App\Models\Plan;
use App\Models\Member;

class PublicReportController extends Controller
{
    public function index(Request $request)
    {
        // 🔒 Cek sesi area (keamanan wajib)
        if (!session()->has('area_authenticated') || !session('area_authenticated')) {
            return redirect()->route('login.form')
                ->withErrors(['loginError' => 'Silakan login sebagai Area terlebih dahulu.']);
        }

        // Ambil ID area dari sesi
        $areaId = session('area_id');
        $areaName = session('area_name');

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateString = $date->format('Y-m-d');

        // 🔥 Tambahkan filter berdasarkan Id_Area
        $scans = Scan::whereDate('Time_Scan', $dateString)
            ->where('Id_Area', $areaId) // ← INI YANG KURANG
            ->with(['member', 'tractor'])
            ->whereHas('tractor')
            ->orderBy('Time_Scan', 'desc')
            ->get();

        // Ambil Plan
        $planMap = [];
        $uniqueKeys = [];
        foreach ($scans as $scan) {
            $key = $scan->Sequence_No_Plan . '_' . $scan->Production_Date_Plan;
            if (!isset($uniqueKeys[$key])) {
                $uniqueKeys[$key] = true;
                $plan = Plan::where('Sequence_No_Plan', $scan->Sequence_No_Plan)
                    ->where('Production_Date_Plan', $scan->Production_Date_Plan)
                    ->first();
                if ($plan) {
                    $planMap[$key] = $plan;
                }
            }
        }

        foreach ($scans as $scan) {
            $key = $scan->Sequence_No_Plan . '_' . $scan->Production_Date_Plan;
            $scan->plan = $planMap[$key] ?? null;
        }

        // Ambil nama member pengganti
        $nikReplaces = $scans->pluck('Nik_Replace')->filter()->unique();
        $memberMap = [];
        if ($nikReplaces->isNotEmpty()) {
            $memberMap = Member::whereIn('nik', $nikReplaces)
                ->pluck('nama', 'nik')
                ->toArray();
        }

        return view('publics.report', compact(
            'scans',
            'dateString',
            'memberMap',
            'areaName' // pastikan dikirim ke view
        ));
    }
}
