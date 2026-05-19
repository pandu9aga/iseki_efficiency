<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WorkDay;
use Illuminate\Http\Request;

class WorkDayController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->get('tahun', date('Y'));
        $workDays = WorkDay::whereRaw("SUBSTRING(Moth_Work_Day, 1, 4) = ?", [$tahun])->get();

        $workDayData = [];
        foreach ($workDays as $wd) {
            $workDayData[$wd->Moth_Work_Day] = $wd->Total_Work_Day;
        }

        return view('admins.workdays.index', compact('tahun', 'workDayData'));
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'tahun' => 'required|digits:4',
            'workdays' => 'nullable|array',
            'workdays.*' => 'nullable|integer|min:0'
        ]);

        $tahun = $request->tahun;
        $input = $request->workdays ?? [];

        $bulanList = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
        foreach ($bulanList as $bulan) {
            $key = "$tahun-$bulan";
            $nilai = $input[$key] ?? null;

            if ($nilai === null || $nilai === '') {
                WorkDay::where('Moth_Work_Day', $key)->delete();
            } else {
                WorkDay::updateOrCreate(
                    ['Moth_Work_Day' => $key],
                    ['Total_Work_Day' => (int) $nilai]
                );
            }
        }

        return redirect()->route('admins.workdays.index', ['tahun' => $tahun])
            ->with('success', 'Data hari kerja berhasil diperbarui.');
    }
}
