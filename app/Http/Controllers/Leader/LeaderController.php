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

        // ✅ Ambil jumlah member aktif dari list_member (sama seperti admin)
        $currentTotalMembers = ListMember::count();

        $scans = Scan::whereDate('Time_Scan', $dateString)->with('tractor')->get();
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();

        // ✅ Hitung Non-Operational Impact (sama seperti admin)
        $costImpactTotal = $costs->sum('Non_Operational_Cost') * $currentTotalMembers;
        $costImpactList = $costs->map(function ($cost) use ($currentTotalMembers) {
            return [
                'label' => $cost->Keterangan_Cost ?? 'Unknown',
                'value' => (float) $cost->Non_Operational_Cost * $currentTotalMembers,
            ];
        })->toArray();

        // Ambil report historis (jika ada)
        $report = Report::where('Day_Report', $dateString)->first();
        $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();
        $powerTotal = $powers->sum('Leave_Hour_Power');

        // Hitung member hours (realtime atau historis)
        if ($isToday) {
            $now = Carbon::now();
            $start = Carbon::today()->setTime(7, 30);
            $endOfWork = Carbon::today()->setTime(16, 30);

            if ($now->lt($start)) {
                $memberHours = 0.0;
            } elseif ($now->gt($endOfWork)) {
                $memberHours = $reportMembers * 8.0;
            } else {
                $totalHours = $start->diffInRealSeconds($now) / 3600.0;

                if ($now->gt(Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                if ($now->gt(Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                if ($now->gt(Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;

                $totalHours = max(0, $totalHours);
                $memberHours = $reportMembers * min($totalHours, 8.0);
            }
        } else {
            $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
        }

        // ✅ Format jam-menit (sama seperti admin)
        $memberHoursText = $this->formatHoursToText($memberHours);

        return view('leaders.dashboard', compact(
            'scans',
            'costs',
            'memberHours',
            'memberHoursText',
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString',
            'isToday',
            'currentTotalMembers', // ✅ kirim ke view
            'costImpactList',
            'costImpactTotal'
        ));
    }

    public function fullscreen(Request $request)
    {
        // Sama seperti index(), tapi tampilan minimal (tanpa sidebar, header)
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $dateString = $date->format('Y-m-d');
        $isToday = $date->isToday();

        // Ambil data seperti biasa
        $scans = Scan::whereDate('Time_Scan', $dateString)->with('tractor')->get();
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();

        // Hitung impact
        $currentTotalMembers = ListMember::count();
        $costImpactTotal = $costs->sum('Non_Operational_Cost') * $currentTotalMembers;
        $costImpactList = $costs->map(function ($cost) use ($currentTotalMembers) {
            return [
                'label' => $cost->Keterangan_Cost ?? 'Unknown',
                'value' => (float) $cost->Non_Operational_Cost * $currentTotalMembers,
            ];
        })->toArray();

        $report = Report::where('Day_Report', $dateString)->first();
        $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();
        $powerTotal = $powers->sum('Leave_Hour_Power');

        // Hitung jam member
        if ($isToday) {
            $now = Carbon::now();
            $start = Carbon::today()->setTime(7, 30);
            $endOfWork = Carbon::today()->setTime(16, 30);

            if ($now->lt($start)) {
                $memberHours = 0.0;
            } elseif ($now->gt($endOfWork)) {
                $memberHours = $reportMembers * 8.0;
            } else {
                $totalHours = $start->diffInRealSeconds($now) / 3600.0;

                if ($now->gt(Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                if ($now->gt(Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                if ($now->gt(Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;

                $totalHours = max(0, $totalHours);
                $memberHours = $reportMembers * min($totalHours, 8.0);
            }
        } else {
            $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
        }

        $scanTotal = $scans->sum('Assigned_Hour_Scan');
        $costTotal = $costs->sum('Non_Operational_Cost') * $currentTotalMembers; // Impact
        $powerTotalCalculated = $powers->sum('Leave_Hour_Power');
        $penangananTotal = $penanganans->sum('Hour_Penanganan');
        $reportNetHours = $memberHours - $powerTotalCalculated;

        return view('leaders.dashboard-fullscreen', compact(
            'scans',
            'costImpactList',
            'memberHours',
            'reportNetHours',
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString',
            'isToday',
            'currentTotalMembers',
            'scanTotal',
            'costTotal',
            'penangananTotal'
        ));
    }

    // ✅ Tambahkan helper ini di dalam LeaderController
    private function formatHoursToText(float $totalHours): string
    {
        if ($totalHours <= 0) return '0 jam 0 menit';
        $hours = floor($totalHours);
        $minutes = round(($totalHours - $hours) * 60);
        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return "{$hours} jam {$minutes} menit";
    }
}
