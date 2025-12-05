<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Report;
use App\Models\ListMember; // hanya untuk count()
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Member;
use App\Models\Scan;

class LeaderReportController extends Controller
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

        return view('leaders.reports.index', compact(
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

    // Simpan Report untuk hari ini berdasarkan jumlah member AKTIF saat ini
    public function storeReport(Request $request)
    {
        $date = Carbon::parse($request->date)->format('Y-m-d');
        $totalMembers = ListMember::count();
        $totalHours = round($totalMembers * 8, 2);

        // Upsert: buat atau update
        Report::updateOrCreate(
            ['Day_Report' => $date], // kondisi
            [
                'Total_Hours_Report' => $totalHours,
                'Total_Member_Report' => $totalMembers,
            ]
        );

        return redirect()->back()->with('success', 'Report berhasil ' . (Report::where('Day_Report', $date)->count() > 1 ? 'diperbarui' : 'disimpan') . '.');
    }

    // COSTW
    public function storeCost(Request $request)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        Cost::create([
            'Non_Operational_Cost' => $request->Non_Operational_Cost,
            'Keterangan_Cost' => $request->Keterangan_Cost,
            'Start_Cost' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Cost berhasil ditambahkan.');
    }

    public function updateCost(Request $request, Cost $cost)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        $cost->update([
            'Non_Operational_Cost' => $request->Non_Operational_Cost,
            'Keterangan_Cost' => $request->Keterangan_Cost,
            'Start_Cost' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Cost berhasil diperbarui.');
    }

    public function destroyCost(Cost $cost)
    {
        $cost->delete();
        return redirect()->back()->with('success', 'Cost berhasil dihapus.');
    }

    // POWER
    public function storePower(Request $request)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        Power::create([
            'Id_Member' => $request->Id_Member,
            'Leave_Hour_Power' => $request->Leave_Hour_Power,
            'Keterangan_Power' => $request->Keterangan_Power,
            'Start_Power' => $timestamp,
        ]);
        return redirect()->back()->with('success', 'Permission berhasil ditambahkan.');
    }

    public function updatePower(Request $request, Power $power)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        $power->update([
            'Id_Member' => $request->Id_Member,
            'Leave_Hour_Power' => $request->Leave_Hour_Power,
            'Keterangan_Power' => $request->Keterangan_Power,
            'Start_Power' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Permission berhasil diperbarui.');
    }

    public function destroyPower(Power $power)
    {
        $power->delete();
        return redirect()->back()->with('success', 'Permission berhasil dihapus.');
    }

    // PENANGANAN
    public function storePenanganan(Request $request)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        Penanganan::create([
            'Hour_Penanganan' => $request->Hour_Penanganan,
            'Keterangan_Penanganan' => $request->Keterangan_Penanganan,
            'Start_Penanganan' => $timestamp,
        ]);
        return redirect()->back()->with('success', 'Time handling berhasil ditambahkan.');
    }

    public function updatePenanganan(Request $request, Penanganan $penanganan)
    {
        $timestamp = $request->date_part . ' ' . ($request->time_part ? $request->time_part . ':00' : '00:00:00');

        $power->update([
            'Hour_Penanganan' => $request->Hour_Penanganan,
            'Keterangan_Penanganan' => $request->Keterangan_Penanganan,
            'Start_Penanganan' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Time handling berhasil diperbarui.');
    }

    public function destroyPenanganan(Penanganan $penanganan)
    {
        $penanganan->delete();
        return redirect()->back()->with('success', 'Time handling berhasil dihapus.');
    }
}