<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Replacement;
use App\Models\Assistance;
use Carbon\Carbon;

class MonitorController extends Controller
{
    /**
     * Tampilkan halaman monitoring Pergantian (Replacements)
     */
    public function replacements(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));

        $query = Replacement::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $replacements = $query->orderBy('created_at', 'desc')->get();

        return view('leaders.monitoring.replacements', compact('replacements', 'filterType', 'filterDate', 'filterMonth'));
    }

    /**
     * Tampilkan halaman monitoring Perbantuan (Assistances)
     */
    public function assistances(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));

        $query = Assistance::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $assistances = $query->orderBy('created_at', 'desc')->get();

        return view('leaders.monitoring.assistances', compact('assistances', 'filterType', 'filterDate', 'filterMonth'));
    }
}
