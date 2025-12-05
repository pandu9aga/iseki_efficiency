<?php

namespace App\Http\Controllers\Admin;

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

class AdminController extends Controller
{
    public function index(Request $request)
    {
        // Ambil tanggal dari request, default hari ini
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateString = $date->format('Y-m-d');

        $scans = Scan::whereDate('Time_Scan', $dateString)
            ->with('tractor')
            ->get();

        $costs = Cost::whereDate('Start_Cost', $dateString)->get();

        $report = Report::where('Day_Report', $dateString)->first();
        $reportHours = $report ? (float)$report->Total_Hours_Report : 0;
        $reportMembers = $report ? (int)$report->Total_Member_Report : 0;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();

        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();

        $powerTotal = $powers->sum('Leave_Hour_Power');

        return view('admins.dashboard', compact(
            'scans',
            'costs',
            'reportHours',
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString' // Kirim tanggal ke view
        ));
    }
}
