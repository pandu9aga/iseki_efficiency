<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Replacement;
use App\Models\Assistance;
use App\Models\ReplacementDuration;
use App\Models\AssistanceDuration;
use App\Models\Member;
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

        // Query durasi pergantian sesuai filter
        $durationQuery = ReplacementDuration::query();
        if ($filterType === 'daily') {
            $durationQuery->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $durationQuery->whereBetween('created_at', [$start, $end]);
        }
        $durations = $durationQuery->orderBy('created_at', 'asc')->get();

        // Group durasi per NIK + Id_Daily_Job, simpan detail per sesi
        $durationSummary = $durations->groupBy(function ($d) {
            return $d->NIK_Replacement . '|' . $d->Id_Daily_Job;
        })->map(function ($group) {
            $first = $group->first();
            $totalMinutes = $group->sum('Total_Minutes');
            $member = Member::where('nik', $first->NIK_Replacement)->first();
            $dailyJob = \App\Models\DailyJob::with('member')->find($first->Id_Daily_Job);

            // Detail per sesi
            $sessions = $group->values()->map(function ($item, $idx) {
                return [
                    'sesi' => $idx + 1,
                    'menit' => $item->Total_Minutes,
                    'waktu' => $item->created_at ? $item->created_at->format('d M Y, H:i') : '-',
                ];
            });

            return [
                'nik' => $first->NIK_Replacement,
                'nama_pengganti' => $member ? $member->nama : $first->NIK_Replacement,
                'nama_pic' => $dailyJob && $dailyJob->member ? $dailyJob->member->nama : '-',
                'total_minutes' => $totalMinutes,
                'jam' => floor($totalMinutes / 60),
                'menit' => $totalMinutes % 60,
                'jumlah_sesi' => $group->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return view('leaders.monitoring.replacements', compact('replacements', 'filterType', 'filterDate', 'filterMonth', 'durationSummary'));
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

        // Query durasi perbantuan sesuai filter
        $durationQuery = AssistanceDuration::query();
        if ($filterType === 'daily') {
            $durationQuery->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $durationQuery->whereBetween('created_at', [$start, $end]);
        }
        $durations = $durationQuery->orderBy('created_at', 'asc')->get();

        // Group durasi per NIK + Id_Daily_Job, simpan detail per sesi
        $durationSummary = $durations->groupBy(function ($d) {
            return $d->NIK_Assistance . '|' . $d->Id_Daily_Job;
        })->map(function ($group) {
            $first = $group->first();
            $totalMinutes = $group->sum('Total_Minutes');
            $member = Member::where('nik', $first->NIK_Assistance)->first();
            $dailyJob = \App\Models\DailyJob::with('member')->find($first->Id_Daily_Job);

            // Detail per sesi
            $sessions = $group->values()->map(function ($item, $idx) {
                return [
                    'sesi' => $idx + 1,
                    'menit' => $item->Total_Minutes,
                    'waktu' => $item->created_at ? $item->created_at->format('d M Y, H:i') : '-',
                ];
            });

            return [
                'nik' => $first->NIK_Assistance,
                'nama_pembantu' => $member ? $member->nama : $first->NIK_Assistance,
                'nama_pic' => $dailyJob && $dailyJob->member ? $dailyJob->member->nama : '-',
                'total_minutes' => $totalMinutes,
                'jam' => floor($totalMinutes / 60),
                'menit' => $totalMinutes % 60,
                'jumlah_sesi' => $group->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return view('leaders.monitoring.assistances', compact('assistances', 'filterType', 'filterDate', 'filterMonth', 'durationSummary'));
    }
}
