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

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateString = $date->format('Y-m-d');
        $timestamp = $date->format('Y-m-d H:i:s');

        // Data yang sudah direkam (dari tabel reports)
        $recordedReport = Report::where('Day_Report', $dateString)->first();
        $reportExists = $recordedReport !== null;

        // Data saat ini (live dari ListMember)
        $currentTotalMembers = ListMember::count();
        $currentTotalHours = round($currentTotalMembers * 8, 2);

        // Ambil data historis lainnya
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();
        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();
        $allMembers = Member::orderBy('nama')->get();

        // Ambil data scan berdasarkan tanggal yang sama
        $scans = Scan::whereDate('Time_Scan', $dateString)
            ->with('member', 'tractor')
            ->orderBy('Time_Scan', 'desc')
            ->get();

        return view('admins.reports.index', compact(
            'dateString',
            'timestamp',
            'reportExists',
            'recordedReport',
            'currentTotalMembers',
            'currentTotalHours',
            'costs',
            'powers',
            'penanganans',
            'allMembers',
            'scans'
        ));
    }
}