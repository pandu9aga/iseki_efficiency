<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Scan;

class MemberReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        $dateString = $date->format('Y-m-d');

        $scans = Scan::whereDate('Time_Scan', $dateString)
            ->with('member', 'tractor')
            ->orderBy('Time_Scan', 'desc')
            ->get();

        return view('members.reports.index', compact('scans', 'dateString'));
    }
}