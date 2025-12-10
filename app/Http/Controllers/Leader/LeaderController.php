<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Report;
use App\Models\ListMember;
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Member;
use App\Models\Scan;

class LeaderController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $dateString = $date->format('Y-m-d');
        $isToday = $date->isToday();

        // Ambil data umum
        $scans = Scan::whereDate('Time_Scan', $dateString)->with('tractor')->get();
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();
        $report = Report::where('Day_Report', $dateString)->first();
        $reportMembers = $report ? (int) $report->Total_Member_Report : 0;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();
        $powerTotal = $powers->sum('Leave_Hour_Power');

        // 🔸 Hitung Member Hours (Real-time untuk hari ini, historis untuk tanggal lalu)
        if ($isToday) {
            $now = Carbon::now();
            $start = Carbon::today()->setTime(7, 30); // 07.30
            $endOfWork = Carbon::today()->setTime(16, 30); // 16.30

            if ($now->lt($start)) {
                $memberHours = 0.0;
            } elseif ($now->gt($endOfWork)) {
                $memberHours = $reportMembers * 8.0;
            } else {
                // 🔸 Hitung total jam sejak 07.30
                $totalHours = $start->diffInRealSeconds($now) / 3600.0;

                // 🔸 Kurangi jeda istirahat (dalam jam)
                if ($now->gt(Carbon::today()->setTime(10, 0))) {
                    $totalHours -= (10 / 60); // 10 menit = 0.1667 jam
                }
                if ($now->gt(Carbon::today()->setTime(12, 0))) {
                    $totalHours -= (40 / 60); // 40 menit = 0.6667 jam
                }
                if ($now->gt(Carbon::today()->setTime(15, 0))) {
                    $totalHours -= (10 / 60); // 10 menit = 0.1667 jam
                }

                // Pastikan tidak negatif
                $totalHours = max(0, $totalHours);

                $memberHours = $reportMembers * $totalHours;
            }
        } else {
            $memberHours = $report ? (float) $report->Total_Hours_Report : 0.0;
        }
        return view('leaders.dashboard', compact(
            'scans',
            'costs',
            'memberHours',       // ✅ bukan reportHours
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString',
            'isToday'            // ✅ penting untuk info real-time
        ));
    }
}
