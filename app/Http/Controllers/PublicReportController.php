<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Scan;
use App\Models\Plan;

class PublicReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateString = $date->format('Y-m-d');

        // Ambil scan dengan relasi
        $scans = Scan::whereDate('Time_Scan', $dateString)
            ->with(['member', 'tractor']) // Ambil relasi biasa
            ->whereHas('tractor') // Hanya scan dengan tractor valid
            ->orderBy('Time_Scan', 'desc')
            ->get();

        // 🔥 Ambil data Plan terkait dalam satu query
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

        // Tambahkan data plan ke setiap scan
        foreach ($scans as $scan) {
            $key = $scan->Sequence_No_Plan . '_' . $scan->Production_Date_Plan;
            $scan->plan = $planMap[$key] ?? null;
        }

        return view('publics.report', compact('scans', 'dateString'));
    }
}