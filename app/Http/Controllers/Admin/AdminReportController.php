<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Report;
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Member;
use App\Models\Scan;
use App\Models\DailyJob;
use App\Models\Area;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Response;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');

            $areaReports = Report::whereDate('Day_Report', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Day_Report', '<=', $endDate->format('Y-m-d'))->get();
            $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))->with('area')->get();
            $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))->with('member', 'area')->get();
            $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))->with('area')->get();
            
            $scans = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                ->with(['tractor', 'dailyJob.area'])
                ->orderBy('Time_Scan', 'desc')
                ->get();

            // We just set these to 0/empty for monthly view since they are daily concepts
            $currentMembersPerArea = collect();
            $currentTotalMembers = 0;
            $currentTotalHours = 0;
            $activeMembers = collect();
            $activeMembersByArea = [];
            $areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today()->startOfDay();

            $dateString = $date->format('Y-m-d');
            $productionDateYmd = $date->format('Ymd');

            $areaReports = Report::where('Day_Report', $date->format('Y-m-d'))->get();

            $currentMembersPerArea = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->select('Id_Area', DB::raw('COUNT(DISTINCT Nik_Daily_Job) as total'))
                ->groupBy('Id_Area')
                ->pluck('total', 'Id_Area');

            $currentTotalMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->distinct('Nik_Daily_Job')
                ->count();
            $currentTotalHours = round($currentTotalMembers * 8, 2);

            $costs = Cost::whereDate('Start_Cost', $date->format('Y-m-d'))->with('area')->get();
            $powers = Power::whereDate('Start_Power', $date->format('Y-m-d'))->with('member', 'area')->get();
            $penanganans = Penanganan::whereDate('Start_Penanganan', $date->format('Y-m-d'))->with('area')->get();

            $dailyJobNiks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->pluck('Nik_Daily_Job')
                ->unique();
            $activeMembers = Member::whereIn('nik', $dailyJobNiks)->get();

            $areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
            $activeMembersByArea = [];
            foreach ($areas as $area) {
                $nks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $area->Id_Area)
                    ->pluck('Nik_Daily_Job')
                    ->unique();
                $members = Member::whereIn('nik', $nks)->get();
                $activeMembersByArea[$area->Id_Area] = $members;
            }

            $scans = Scan::whereDate('Time_Scan', $date->format('Y-m-d'))
                ->with(['tractor', 'dailyJob.area'])
                ->orderBy('Time_Scan', 'desc')
                ->get();
        }

        // 🔥 Ambil NIK pengganti unik
        $nikReplaces = $scans->pluck('Nik_Replace')->filter()->unique()->values();
        $memberMap = [];
        if ($nikReplaces->isNotEmpty()) {
            $memberMap = Member::whereIn('nik', $nikReplaces)
                ->pluck('nama', 'nik')
                ->toArray();
        }

        // ✅ Tambahkan ini: ambil semua member untuk modal "Add Handling"
        $allMembers = Member::with('area')->get();

        // ✅ Map NIK => nama untuk tampilan popover cost
        $allNiks = Member::pluck('nama', 'nik')->toArray();

        return view('admins.reports.index', compact(
            'dateString',
            'areaReports',
            'currentMembersPerArea',
            'currentTotalMembers',
            'currentTotalHours',
            'costs',
            'powers',
            'penanganans',
            'activeMembers',
            'activeMembersByArea',
            'scans',
            'areas',
            'memberMap',
            'allMembers',
            'allNiks'
        ));
    }

    public function storeReport(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'date' => 'required|date',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $date = Carbon::parse($request->date)->format('Y-m-d');
        $productionDateYmd = Carbon::parse($request->date)->format('Ymd');
        $areaId = $request->Id_Area;

        $totalMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
            ->where('Id_Area', $areaId)
            ->distinct('Nik_Daily_Job')
            ->count();
        $totalHours = round($totalMembers * 8, 2);

        $existing = Report::where('Day_Report', $date)
            ->where('Id_Area', $areaId)
            ->exists();

        Report::updateOrCreate(
            [
                'Day_Report' => $date,
                'Id_Area' => $areaId
            ],
            [
                'Total_Hours_Report' => $totalHours,
                'Total_Member_Report' => $totalMembers,
            ]
        );

        $message = $existing
            ? 'Report untuk area berhasil diperbarui.'
            : 'Report untuk area berhasil disimpan.';

        return redirect()->back()->with('success', $message);
    }

    // COST — ✅ DIPERBAIKI
    public function storeCost(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'kategori_cost' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'Id_Area' => 'required|exists:areas,Id_Area',
            'jam_cost' => 'required|integer|min:0',
            'menit_cost' => 'required|integer|min:0|max:59',
            'selected_members' => 'nullable|array',
            'selected_members.*' => 'string',
        ]);

        // 🔥 KATEGORI BARU — SESUAI YANG DIMINTA
        $mapKategoriCost = [
            'senam' => 'Senam',
            'meeting_maneger' => '課長朝礼 (meeting maneger)',
            'meeting_maneger_dept' => '部長朝礼 (meeting maneger Dept)',
            'meeting_pres_dir' => '社長朝礼 (meeting Pres.Dir)',
            'meeting_team_awal' => '組内最初ミーティング (meeting team dijam awal)',
            'meeting_team_akhir' => '組内最後ミーティング (meeting team dijam akhir)',
            'kebersihan_team' => '組内清掃 (kebersihan team)',
            'check_sheet' => 'チェックシートの点検 (pengecekan check sheet)',
            'pelatihan_pekerja' => '作業者教育 (pelatihan pekerja)',
            'pengecekkan_type_jarang_nagalir' => 'あまり流れてない機械確認 (Pengecekkan Type Jarang Ngalir)',
            'line_stop_divisi_lain' => '他部署責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab Divsi lain)',
            'line_stop_team_lain' => '他チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team lain)',
            'line_stop_team_sendiri' => '自チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team sendiri)',
            'lain_lain' => 'Lain-lain',
        ];

        if ($request->kategori_cost === 'lain_lain') {
            $request->validate(['Keterangan_Cost' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Cost;
        } else {
            // Jika tidak ada di map, tetap simpan value asli (aman)
            $keterangan = $mapKategoriCost[$request->kategori_cost] ?? $request->kategori_cost;
        }

        $dateYm = Carbon::parse($request->date_part)->format('Y-m-d');
        $productionDateYmd = Carbon::parse($request->date_part)->format('Ymd');
        $areaId = $request->Id_Area;

        $allActiveNiks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
            ->where('Id_Area', $areaId)
            ->pluck('Nik_Daily_Job')
            ->unique()
            ->toArray();

        if (empty($allActiveNiks)) {
            return back()->withErrors(['selected_members' => 'No active members found in this area on ' . $dateYm]);
        }

        $selectedNiks = $request->input('selected_members', []);

        if (empty($selectedNiks)) {
            // Simpan semua NIK aktif sebagai array (bukan string 'all')
            $appliedNiks = array_values($allActiveNiks);
            $memberCount = count($appliedNiks);
        } else {
            $appliedNiks = array_values(array_intersect($selectedNiks, $allActiveNiks));
            $memberCount = count($appliedNiks);
            if ($memberCount === 0) {
                return back()->withErrors(['selected_members' => 'Selected members are not active in this area.']);
            }
        }

        $durationPerPerson = (float) $request->jam_cost + ((float) $request->menit_cost / 60);
        $finalCost = $durationPerPerson * $memberCount;

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        Cost::create([
            'Non_Operational_Cost' => round($finalCost, 2),
            'Keterangan_Cost' => $keterangan,
            'Start_Cost' => $timestamp,
            'Id_Area' => $areaId,
            'applied_members' => $appliedNiks,
        ]);

        $msg = $memberCount . ' member' . ($memberCount > 1 ? 's' : '');
        return redirect()->back()->with('success', "Cost berhasil ditambahkan (applied to $msg).");
    }

    public function updateCost(Request $request, Cost $cost)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'kategori_cost' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'Id_Area' => 'required|exists:areas,Id_Area',
            'jam_cost' => 'required|integer|min:0',
            'menit_cost' => 'required|integer|min:0|max:59',
            'selected_members' => 'nullable|array',
            'selected_members.*' => 'string',
        ]);

        $mapKategoriCost = [
            'senam' => 'Senam',
            'meeting_maneger' => '課長朝礼 (meeting maneger)',
            'meeting_maneger_dept' => '部長朝礼 (meeting maneger Dept)',
            'meeting_pres_dir' => '社長朝礼 (meeting Pres.Dir)',
            'meeting_team_awal' => '組内最初ミーティング (meeting team dijam awal)',
            'meeting_team_akhir' => '組内最後ミーティング (meeting team dijam akhir)',
            'kebersihan_team' => '組内清掃 (kebersihan team)',
            'check_sheet' => 'チェックシートの点検 (pengecekan check sheet)',
            'pelatihan_pekerja' => '作業者教育 (pelatihan pekerja)',
            'pengecekkan_type_jarang_nagalir' => 'あまり流れてない機械確認 (Pengecekkan Type Jarang Ngalir)',
            'line_stop_divisi_lain' => '他部署責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab Divsi lain)',
            'line_stop_team_lain' => '他チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team lain)',
            'line_stop_team_sendiri' => '自チーム責任によるﾗｲﾝｽﾄｯﾌﾟ (line stop sebab team sendiri)',
            'lain_lain' => 'Lain-lain',
        ];

        if ($request->kategori_cost === 'lain_lain') {
            $request->validate(['Keterangan_Cost' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Cost;
        } else {
            $keterangan = $mapKategoriCost[$request->kategori_cost] ?? $request->kategori_cost;
        }

        $dateYm = Carbon::parse($request->date_part)->format('Y-m-d');
        $productionDateYmd = Carbon::parse($request->date_part)->format('Ymd');
        $areaId = $request->Id_Area;

        $allActiveNiks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
            ->where('Id_Area', $areaId)
            ->pluck('Nik_Daily_Job')
            ->unique()
            ->toArray();

        if (empty($allActiveNiks)) {
            return back()->withErrors(['selected_members' => 'No active members found in this area on ' . $dateYm]);
        }

        $selectedNiks = $request->input('selected_members', []);

        if (empty($selectedNiks)) {
            // Simpan semua NIK aktif sebagai array (bukan string 'all')
            $appliedNiks = array_values($allActiveNiks);
            $memberCount = count($appliedNiks);
        } else {
            $appliedNiks = array_values(array_intersect($selectedNiks, $allActiveNiks));
            $memberCount = count($appliedNiks);
            if ($memberCount === 0) {
                return back()->withErrors(['selected_members' => 'Selected members are not active in this area.']);
            }
        }

        $durationPerPerson = (float) $request->jam_cost + ((float) $request->menit_cost / 60);
        $finalCost = $durationPerPerson * $memberCount;

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        $cost->update([
            'Non_Operational_Cost' => round($finalCost, 2),
            'Keterangan_Cost' => $keterangan,
            'Start_Cost' => $timestamp,
            'Id_Area' => $areaId,
            'applied_members' => $appliedNiks,
        ]);

        $msg = $memberCount . ' member' . ($memberCount > 1 ? 's' : '');
        return redirect()->back()->with('success', "Cost berhasil diperbarui (applied to $msg).");
    }

    public function destroyCost(Cost $cost)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }
        $cost->delete();
        return redirect()->back()->with('success', 'Cost berhasil dihapus.');
    }

    // POWER
    public function storePower(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'Id_Member' => 'required|exists:rifa.employees,id',
            'Leave_Hour_Power' => 'required|numeric|min:0',
            'Keterangan_Power' => 'required|string|max:255',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        Power::create([
            'Id_Member' => $request->Id_Member,
            'Leave_Hour_Power' => $request->Leave_Hour_Power,
            'Keterangan_Power' => $request->Keterangan_Power,
            'Start_Power' => $timestamp,
            'Id_Area' => $request->Id_Area,
        ]);

        return redirect()->back()->with('success', 'Permission berhasil ditambahkan.');
    }

    public function updatePower(Request $request, Power $power)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'Id_Member' => 'required|exists:rifa.employees,id',
            'Leave_Hour_Power' => 'required|numeric|min:0',
            'Keterangan_Power' => 'required|string|max:255',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        $power->update([
            'Id_Member' => $request->Id_Member,
            'Leave_Hour_Power' => $request->Leave_Hour_Power,
            'Keterangan_Power' => $request->Keterangan_Power,
            'Start_Power' => $timestamp,
            'Id_Area' => $request->Id_Area,
        ]);

        return redirect()->back()->with('success', 'Permission berhasil diperbarui.');
    }

    public function destroyPower(Power $power)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }
        $power->delete();
        return redirect()->back()->with('success', 'Permission berhasil dihapus.');
    }

    // PENANGANAN — ✅ DIPERBAIKI
    public function storePenanganan(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'jam_penanganan' => 'required|integer|min:0',
            'menit_penanganan' => 'required|integer|min:0|max:59',
            'kategori_penanganan' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'catatan_internal' => 'nullable|string|max:255',
            'Id_Area' => 'required|exists:areas,Id_Area',
            'selected_members_area' => 'nullable|array',
            'selected_members_all' => 'nullable|array',
        ]);

        $areaMembers = $request->input('selected_members_area', []);
        $allMembersInput = $request->input('selected_members_all', []);
        $selectedNiks = array_unique(array_merge($areaMembers, $allMembersInput));

        if (empty($selectedNiks)) {
            return back()->withErrors(['selected_members' => 'Pilih minimal 1 member dari salah satu daftar.']);
        }

        $mapKategoriPenanganan = [
            'fix_back_up_proses' => 'Fix Back Up Proses',
            'back_up_absensi' => 'Back Up Absensi',
            'bantuan_pic_absensi' => 'Bantuan ke PIC Absensi',
            'back_up_line_stop_irregular' => 'Back Up Line Stop / Irregular',
            'perbantuan_area_lain' => 'Perbantuan area lain 【－】',
            'lembur_produksi' => 'Lembur Produksi',
            'lembur_mente' => 'Lembur Mente',
            'lain_lain' => 'Lain-lain',
        ];

        if ($request->kategori_penanganan === 'lain_lain') {
            $request->validate(['Keterangan_Penanganan' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Penanganan;
        } else {
            $keterangan = $mapKategoriPenanganan[$request->kategori_penanganan] ?? $request->kategori_penanganan;
        }

        $jam = (float) $request->jam_penanganan;
        $menit = (float) $request->menit_penanganan;
        $durationPerPerson = $jam + ($menit / 60);
        $memberCount = count($selectedNiks);
        $totalHour = $durationPerPerson * $memberCount;

        if ($request->kategori_penanganan === 'perbantuan_area_lain') {
            $totalHour = -$totalHour;
        }

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        Penanganan::create([
            'Hour_Penanganan' => round($totalHour, 2),
            'Keterangan_Penanganan' => $keterangan,
            'Start_Penanganan' => $timestamp,
            'Id_Area' => $request->Id_Area,
            'Applied_Members' => $selectedNiks,
        ]);
        return redirect()->back()->with('success', 'Time handling berhasil ditambahkan.');
    }

    public function updatePenanganan(Request $request, Penanganan $penanganan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $request->validate([
            'jam_penanganan' => 'required|integer|min:0',
            'menit_penanganan' => 'required|integer|min:0|max:59',
            'kategori_penanganan' => 'required|string',
            'date_part' => 'required|date',
            'time_part' => 'nullable|date_format:H:i',
            'Id_Area' => 'required|exists:areas,Id_Area',
            'selected_members_area' => 'nullable|array',
            'selected_members_all' => 'nullable|array',
        ]);

        $areaMembers = array_filter((array) $request->input('selected_members_area', []));
        $allMembersInput = array_filter((array) $request->input('selected_members_all', []));
        $selectedNiks = array_values(array_unique(array_merge($areaMembers, $allMembersInput)));

        if (empty($selectedNiks)) {
            return back()->withErrors(['selected_members' => 'Pilih minimal 1 member dari salah satu daftar.']);
        }

        $mapKategoriPenanganan = [
            'fix_back_up_proses' => 'Fix Back Up Proses',
            'back_up_absensi' => 'Back Up Absensi',
            'bantuan_pic_absensi' => 'Bantuan ke PIC Absensi',
            'back_up_line_stop_irregular' => 'Back Up Line Stop / Irregular',
            'perbantuan_area_lain' => 'Perbantuan area lain 【－】',
            'lembur_produksi' => 'Lembur Produksi',
            'lembur_mente' => 'Lembur Mente',
            'lain_lain' => 'Lain-lain',
        ];

        if ($request->kategori_penanganan === 'lain_lain') {
            $request->validate(['Keterangan_Penanganan' => 'required|string|max:255']);
            $keterangan = $request->Keterangan_Penanganan;
        } else {
            $keterangan = $mapKategoriPenanganan[$request->kategori_penanganan] ?? $request->kategori_penanganan;
        }

        $jam = (float) $request->jam_penanganan;
        $menit = (float) $request->menit_penanganan;
        $durationPerPerson = $jam + ($menit / 60);
        $memberCount = count($selectedNiks);
        $totalHour = $durationPerPerson * $memberCount;

        if ($request->kategori_penanganan === 'perbantuan_area_lain') {
            $totalHour = -$totalHour;
        }

        $timestamp = Carbon::createFromFormat('Y-m-d H:i', $request->date_part . ' ' . ($request->time_part ?? '07:30'))
            ->tz('Asia/Jakarta')
            ->format('Y-m-d H:i:s');

        $penanganan->update([
            'Hour_Penanganan' => round($totalHour, 2),
            'Keterangan_Penanganan' => $keterangan,
            'Start_Penanganan' => $timestamp,
            'Id_Area' => $request->Id_Area,
            'Applied_Members' => $selectedNiks,
        ]);

        return redirect()->back()->with('success', 'Time handling berhasil diperbarui.');
    }

    public function destroyPenanganan(Penanganan $penanganan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $penanganan->delete();
        return redirect()->back()->with('success', 'Time handling berhasil dihapus.');
    }

    public function destroyScan(Request $request, Scan $scan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $scan->delete();
        return redirect()->back()->with('success', 'Scan berhasil dihapus.');
    }

    public function exportReport(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 1) {
            abort(403);
        }

        $isMonthFilter = $request->filled('month');
        $areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $dateLabel = $monthParsed->format('F Y');
            $filePrefix = 'Monthly';
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today()->startOfDay();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $dateLabel = $date->format('d F Y');
            $filePrefix = 'Daily';
        }

        // === FETCH DATA ===
        $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
            ->with('area')->get();
        $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
            ->with('member', 'area')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
            ->with('area')->get();
        $scans = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
            ->with('tractor')->get();

        // === MEMBER NIK MAP ===
        $allNiks = Member::pluck('nama', 'nik')->toArray();

        // === BUILD SPREADSHEET ===
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("{$filePrefix} Report");

        // --- TITLE ---
        $sheet->setCellValue('A1', strtoupper($filePrefix) . ' PRODUCTION REPORT DATA');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('A2', ($isMonthFilter ? 'Bulan' : 'Tanggal') . ': ' . $dateLabel);
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

        $row = 4;

        // ============================================================
        //  SECTION 1: NON OPERATIONAL COST
        // ============================================================
        $sec1Start = $row;
        $sheet->setCellValue("A{$row}", 'NON OPERATIONAL COST');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        // Header
        $headers = ['No', 'Area', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i); // A-E
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E2F3');
        $headerRowCost = $row;
        $row++;

        $no = 1;
        $costStartRow = $row;
        foreach ($costs as $cost) {
            $areaName = $cost->area ? $cost->area->Name_Area : '-';
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $areaName);
            $sheet->setCellValue("C{$row}", $cost->Keterangan_Cost ?? '-');
            $sheet->setCellValue("D{$row}", round((float) $cost->Non_Operational_Cost, 2));
            $sheet->setCellValue("E{$row}", Carbon::parse($cost->Start_Cost)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $costEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", 'TOTAL NON OPERATIONAL');
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        if ($costStartRow <= $costEndRow) {
            $sheet->setCellValue("D{$row}", "=SUM(D{$costStartRow}:D{$costEndRow})");
        } else {
            $sheet->setCellValue("D{$row}", 0);
        }
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
        
        // Border Section 1
        $sheet->getStyle("A{$sec1Start}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $row += 2;

        // ============================================================
        //  SECTION 2: ABSENSI
        // ============================================================
        $sec2Start = $row;
        $sheet->setCellValue("A{$row}", 'ABSENSI / IZIN');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF70AD47');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $headers2 = ['No', 'Area', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers2 as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2EFDA');
        $row++;

        $no = 1;
        $powerStartRow = $row;
        foreach ($powers as $power) {
            $areaName = $power->area ? $power->area->Name_Area : '-';
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $areaName);
            $sheet->setCellValue("C{$row}", $power->Keterangan_Power ?? '-');
            $sheet->setCellValue("D{$row}", round((float) $power->Leave_Hour_Power, 2));
            $sheet->setCellValue("E{$row}", Carbon::parse($power->Start_Power)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $powerEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", 'TOTAL ABSENSI');
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        if ($powerStartRow <= $powerEndRow) {
            $sheet->setCellValue("D{$row}", "=SUM(D{$powerStartRow}:D{$powerEndRow})");
        } else {
            $sheet->setCellValue("D{$row}", 0);
        }
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
        
        // Border Section 2
        $sheet->getStyle("A{$sec2Start}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $row += 2;

        // ============================================================
        //  SECTION 3: PERBANTUAN / PENANGANAN
        // ============================================================
        $sec3Start = $row;
        $sheet->setCellValue("A{$row}", 'PERBANTUAN / PENANGANAN');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFED7D31');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $headers3 = ['No', 'Area', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers3 as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
        $row++;

        $no = 1;
        $penangananStartRow = $row;
        foreach ($penanganans as $p) {
            $areaName = $p->area ? $p->area->Name_Area : '-';
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $areaName);
            $sheet->setCellValue("C{$row}", $p->Keterangan_Penanganan ?? '-');
            $sheet->setCellValue("D{$row}", round((float) $p->Hour_Penanganan, 2));
            $sheet->setCellValue("E{$row}", Carbon::parse($p->Start_Penanganan)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $penangananEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", 'TOTAL PERBANTUAN');
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        if ($penangananStartRow <= $penangananEndRow) {
            $sheet->setCellValue("D{$row}", "=SUM(D{$penangananStartRow}:D{$penangananEndRow})");
        } else {
            $sheet->setCellValue("D{$row}", 0);
        }
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
        
        // Border Section 3
        $sheet->getStyle("A{$sec3Start}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $row += 2;

        // ============================================================
        //  SECTION 4: SCAN TRAKTOR (TOTAL SAJA)
        // ============================================================
        $sec4Start = $row;
        $sheet->setCellValue("A{$row}", 'SCAN TRAKTOR');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF5B9BD5');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $sheet->setCellValue("A{$row}", 'Keterangan');
        $sheet->setCellValue("B{$row}", 'Total Jam (h)');
        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
        $row++;

        $totalScanHours = $scans->sum('Assigned_Hour_Scan');
        $sheet->setCellValue("A{$row}", 'Total Jam Scan Traktor Keseluruhan');
        $sheet->setCellValue("B{$row}", round($totalScanHours, 2));
        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);

        // Border Section 4
        $sheet->getStyle("A{$sec4Start}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row += 2;

        // ============================================================
        //  SECTION 5: RINGKASAN PER AREA
        // ============================================================
        $sec5Start = $row;
        $sheet->setCellValue("A{$row}", 'RINGKASAN PER AREA');
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF7030A0');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $summaryHeaders = ['Area', 'Non Op (h)', 'Absensi (h)', 'Perbantuan (h)', 'Scan Traktor (h)'];
        foreach ($summaryHeaders as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2D1F0');
        $summaryHeaderRow = $row;
        $row++;

        $summaryStartRow = $row;
        foreach ($areas as $area) {
            $areaCost = $costs->where('Id_Area', $area->Id_Area)->sum('Non_Operational_Cost');
            $areaPower = $powers->where('Id_Area', $area->Id_Area)->sum('Leave_Hour_Power');
            $areaPenanganan = $penanganans->where('Id_Area', $area->Id_Area)->sum('Hour_Penanganan');
            $areaScan = $scans->where('Id_Area', $area->Id_Area)->sum('Assigned_Hour_Scan');

            $sheet->setCellValue("A{$row}", $area->Name_Area);
            $sheet->setCellValue("B{$row}", round($areaCost, 2));
            $sheet->setCellValue("C{$row}", round($areaPower, 2));
            $sheet->setCellValue("D{$row}", round($areaPenanganan, 2));
            $sheet->setCellValue("E{$row}", round($areaScan, 2));
            $row++;
        }
        $summaryEndRow = $row - 1;

        // Grand Total
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->setCellValue("{$col}{$row}", "=SUM({$col}{$summaryStartRow}:{$col}{$summaryEndRow})");
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');

        // Border Section 5
        $sheet->getStyle("A{$sec5Start}:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row++;

        // === FORMATTING ===
        $lastRow = $row - 1;

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(14);
        $sheet->getColumnDimension('E')->setWidth(18);

        // Number format for jam columns
        $sheet->getStyle("D1:D{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Vertical center
        $sheet->getStyle("A1:E{$lastRow}")->getAlignment()->setVertical('center');
        $sheet->getStyle("A1:E{$lastRow}")->getAlignment()->setWrapText(true);

        // === DOWNLOAD ===
        $fileName = "{$filePrefix}_Report_Data_{$dateString}.xlsx";
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
