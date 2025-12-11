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
use App\Models\Plan;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $dateString = $date->format('Y-m-d');

        $recordedReport = Report::where('Day_Report', $dateString)->first();
        $reportExists = $recordedReport !== null;

        $currentTotalMembers = ListMember::count();
        $currentTotalHours = round($currentTotalMembers * 8, 2);

        $costs = Cost::whereDate('Start_Cost', $dateString)->get();

        // ✅ HITUNG TOTAL NON-OP YANG SUDAH DIKALIKAN
        $totalNonOpHours = $costs->sum('Non_Operational_Cost') * $currentTotalMembers;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();

        $activeMembers = ListMember::with('member')
            ->get()
            ->filter(fn($lm) => $lm->member !== null)
            ->sortBy('member.nama');

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

        return view('admins.reports.index', compact(
            'dateString',
            'reportExists',
            'recordedReport',
            'currentTotalMembers',
            'currentTotalHours',
            'costs',
            'powers',
            'penanganans',
            'activeMembers',
            'scans',
            'totalNonOpHours' // ✅ TAMBAHKAN INI
        ));
    }

    public function storeReport(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $totalMembers = ListMember::count();
        $totalHours = round($totalMembers * 8, 2);

        $existing = Report::where('Day_Report', $date)->exists();

        Report::updateOrCreate(
            ['Day_Report' => $date],
            [
                'Total_Hours_Report' => $totalHours,
                'Total_Member_Report' => $totalMembers,
            ]
        );

        $message = $existing
            ? 'Report berhasil diperbarui.'
            : 'Report berhasil disimpan.';

        return redirect()->back()->with('success', $message);
    }

    // COST — versi lengkap dengan validasi
    public function storeCost(Request $request)
    {
        $request->validate([
            'Non_Operational_Cost' => 'required|numeric|min:0',
            'kategori_cost' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
        ]);

        // Tentukan deskripsi berdasarkan kategori
        if ($request->kategori_cost === 'lain_lain') {
            $request->validate(['Keterangan_Cost' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Cost;
        } else {
            $map = [
                'senam' => 'Senam',
                'briefing' => 'Briefing',
                'checksheet' => 'Checksheet',
            ];
            $keterangan = $map[$request->kategori_cost] ?? 'Unknown';
        }

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

        Cost::create([
            'Non_Operational_Cost' => $request->Non_Operational_Cost,
            'Keterangan_Cost' => $keterangan,
            'Start_Cost' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Cost berhasil ditambahkan.');
    }


    public function updateCost(Request $request, Cost $cost)
    {
        $request->validate([
            'Non_Operational_Cost' => 'required|numeric|min:0',
            'kategori_cost' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
        ]);

        if ($request->kategori_cost === 'lain_lain') {
            $request->validate(['Keterangan_Cost' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Cost;
        } else {
            $map = [
                'senam' => 'Senam',
                'briefing' => 'Briefing',
                'checksheet' => 'Checksheet',
            ];
            $keterangan = $map[$request->kategori_cost] ?? 'Unknown';
        }

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

        $cost->update([
            'Non_Operational_Cost' => $request->Non_Operational_Cost,
            'Keterangan_Cost' => $keterangan,
            'Start_Cost' => $timestamp,
        ]);

        return redirect()->back()->with('success', 'Cost berhasil diperbarui.');
    }

    public function destroyCost(Cost $cost)
    {
        $cost->delete();
        return redirect()->back()->with('success', 'Cost berhasil dihapus.');
    }

    // POWER — DIPERBAIKI: validasi pakai list_members, bukan members
    public function storePower(Request $request)
    {
        $request->validate([
            'Id_Member' => 'required|exists:list_members,Id_Member', // ✅ PERBAIKAN UTAMA
            'Leave_Hour_Power' => 'required|numeric|min:0',
            'Keterangan_Power' => 'required|string|max:255',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
        ]);

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

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
        $request->validate([
            'Id_Member' => 'required|exists:list_members,Id_Member', // ✅ PERBAIKAN UTAMA
            'Leave_Hour_Power' => 'required|numeric|min:0',
            'Keterangan_Power' => 'required|string|max:255',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
        ]);

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

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

    // PENANGANAN — versi lengkap (dari versi 2)
    public function storePenanganan(Request $request)
    {
        $request->validate([
            'Hour_Penanganan' => 'required|numeric|min:0',
            'Keterangan_Penanganan' => 'required|string|max:255',
            'kategori_penanganan' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'catatan_internal' => 'nullable|string|max:255',
        ]);

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

        $hour = (float) $request->Hour_Penanganan;

        // Jika perbantuan area lain → negatif
        if ($request->kategori_penanganan === 'perbantuan_area_lain') {
            $hour = -$hour;
        }

        Penanganan::create([
            'Hour_Penanganan' => $hour,
            'Keterangan_Penanganan' => $request->Keterangan_Penanganan,
            'Start_Penanganan' => $timestamp,
            'catatan_internal' => $request->catatan_internal,
        ]);

        return redirect()->back()->with('success', 'Time handling berhasil ditambahkan.');
    }

    public function updatePenanganan(Request $request, Penanganan $penanganan)
    {
        $request->validate([
            'Hour_Penanganan' => 'required|numeric|min:0',
            'Keterangan_Penanganan' => 'required|string|max:255',
            'kategori_penanganan' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'catatan_internal' => 'nullable|string|max:255',
        ]);

        $timestamp = Carbon::createFromFormat(
            'Y-m-d H:i',
            $request->date_part . ' ' . ($request->time_part ?? '07:30')
        )->tz('Asia/Jakarta')->format('Y-m-d H:i:s');

        $hour = (float) $request->Hour_Penanganan;

        if ($request->kategori_penanganan === 'perbantuan_area_lain') {
            $hour = -$hour;
        }

        $penanganan->update([
            'Hour_Penanganan' => $hour,
            'Keterangan_Penanganan' => $request->Keterangan_Penanganan,
            'Start_Penanganan' => $timestamp,
            'catatan_internal' => $request->catatan_internal,
        ]);

        return redirect()->back()->with('success', 'Time handling berhasil diperbarui.');
    }

    public function destroyPenanganan(Penanganan $penanganan)
    {
        $penanganan->delete();
        return redirect()->back()->with('success', 'Time handling berhasil dihapus.');
    }
}
