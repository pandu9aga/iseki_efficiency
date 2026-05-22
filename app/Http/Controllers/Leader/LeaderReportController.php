<?php

namespace App\Http\Controllers\Leader;

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
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Response;

class LeaderReportController extends Controller
{
    public function index(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $user = User::with('areas')->findOrFail(session('Id_User'));

        // Check if user has assigned areas
        if ($user->areas->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'Akun Anda belum ditugaskan ke area mana pun.']);
        }

        // Determine active area from request or default to first assigned area
        $activeAreaId = $request->query('area');
        if ($activeAreaId) {
            // Verify if user is assigned to this area
            $activeArea = $user->areas->where('Id_Area', $activeAreaId)->first();
            if (!$activeArea) {
                // If requested area is not assigned, fallback to first assigned
                $activeArea = $user->areas->first();
            }
        } else {
            $activeArea = $user->areas->first();
        }

        $areaId = $activeArea->Id_Area;
        $area = Area::findOrFail($areaId);

        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');

            $areaReports = Report::whereDate('Day_Report', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Day_Report', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->get();
            
            $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('area')
                ->get();

            $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('member', 'area')
                ->get();

            $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('area')
                ->get();

            $scans = Scan::where('Id_Area', $areaId)
                ->whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                ->with(['tractor', 'dailyJob'])
                ->orderBy('Time_Scan', 'desc')
                ->get();

            $currentMembersPerArea = 0;
            $activeMembers = collect();
            $activeMembersByArea = [];
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today()->startOfDay();

            $dateString = $date->format('Y-m-d');
            $productionDateYmd = $date->format('Ymd');

            $areaReports = Report::where('Day_Report', $date->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->get();

            $currentMembersPerArea = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->where('Id_Area', $areaId)
                ->count();

            $costs = Cost::whereDate('Start_Cost', $date->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('area')
                ->get();

            $powers = Power::whereDate('Start_Power', $date->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('member', 'area')
                ->get();

            $penanganans = Penanganan::whereDate('Start_Penanganan', $date->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('area')
                ->get();

            $dailyJobNiks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->where('Id_Area', $areaId)
                ->pluck('Nik_Daily_Job')
                ->unique();
            $activeMembers = Member::whereIn('nik', $dailyJobNiks)->get();
            $activeMembersByArea = [$areaId => $activeMembers];

            $scans = Scan::where('Id_Area', $areaId)
                ->whereDate('Time_Scan', $date->format('Y-m-d'))
                ->with(['tractor', 'dailyJob'])
                ->orderBy('Time_Scan', 'desc')
                ->get();
        }

        $nikReplaces = $scans->pluck('Nik_Replace')->filter()->unique()->values();
        $memberMap = [];
        if ($nikReplaces->isNotEmpty()) {
            $memberMap = Member::whereIn('nik', $nikReplaces)
                ->pluck('nama', 'nik')
                ->toArray();
        }

        // Pass assigned areas for Tabs (instead of single $areas collection)
        $assignedAreas = $user->areas;

        // Note: View expects 'areas' variable for looping in modals (cost, power, etc).
        // For the single active area view, we can pass $assignedAreas as 'areas' if the view iterates it,
        // BUT the view seems to iterate 'areas' to create tab panes.
        // Let's pass $assignedAreas as 'areas' so the view loop works for tabs (if we update view to use it).
        // Actually, let's keep 'areas' as the collection of ALL assigned areas so the view loop generates all tabs?
        // Wait, the controller logic above focuses on ONE active area ($area).
        // Creating tabs purely in view requires iterating all assigned areas.
        // So I should pass $assignedAreas.
        // AND, to minimize view changes, I might rename it to 'areas' if the view uses 'areas' for tabs?
        // The current view uses `@foreach ($areas as $index => $area)` for tabs!
        // So if I pass `$areas = $assignedAreas`, the existing loop might just work if I update the loop logic!
        // BUT I want to change the view to use explicit tabs and then content.
        // Let's pass both: 'assignedAreas' for the top tabs, and 'area' (single) for the content?
        // Or just 'areas' = $assignedAreas.

        // View uses $areas for modals loop.
        $areas = $assignedAreas;

        // 🔥 Ambil SEMUA member dari semua area (untuk dropdown "All Areas")
        $allMembers = Member::with('area')->get();

        // ✅ Map NIK => nama untuk tampilan popover cost
        $allNiks = Member::pluck('nama', 'nik')->toArray();

        return view('leaders.reports.index', compact(
            'dateString',
            'areaReports',
            'currentMembersPerArea',
            'costs',
            'powers',
            'penanganans',
            'activeMembers',
            'activeMembersByArea',
            'scans',
            'area', // The active area
            'memberMap',
            'allMembers',
            'allNiks',
            'assignedAreas', // For the new tabs
            'areas'
        ));
    }

    public function storeReport(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You are not assigned to this area.');
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
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You are not assigned to this area.');
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
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment (Both record and request)
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $cost->Id_Area)) {
            abort(403, 'You cannot edit records from areas you are not assigned to.');
        }

        if ($request->has('Id_Area') && !$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You cannot move records to areas you are not assigned to.');
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
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $cost->Id_Area)) {
            abort(403, 'You cannot delete records from areas you are not assigned to.');
        }
        $cost->delete();
        return redirect()->back()->with('success', 'Cost berhasil dihapus.');
    }

    // POWER
    public function storePower(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You are not assigned to this area.');
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
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment (Both record and request)
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $power->Id_Area)) {
            abort(403, 'You cannot edit records from areas you are not assigned to.');
        }

        if ($request->has('Id_Area') && !$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You cannot move records to areas you are not assigned to.');
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
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $power->Id_Area)) {
            abort(403, 'You cannot delete records from areas you are not assigned to.');
        }
        $power->delete();
        return redirect()->back()->with('success', 'Permission berhasil dihapus.');
    }

    // PENANGANAN — DIPERBAIKI DENGAN FITUR MEMBER MULTI-AREA
    // PENANGANAN — DIPERBAIKI: KONSISTEN, VALIDASI BENAR, SUPPORT 2 DROPDOWN
    public function storePenanganan(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You are not assigned to this area.');
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
        $allMembers = $request->input('selected_members_all', []);
        $selectedNiks = array_unique(array_merge($areaMembers, $allMembers));

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
            'Applied_Members' => $selectedNiks, // ✅ pastikan nama kolom sesuai DB
        ]);
        return redirect()->back()->with('success', 'Time handling berhasil ditambahkan.');
    }

    public function updatePenanganan(Request $request, Penanganan $penanganan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment (Both record and request)
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $penanganan->Id_Area)) {
            abort(403, 'You cannot edit records from areas you are not assigned to.');
        }

        if ($request->has('Id_Area') && !$user->areas->contains('Id_Area', $request->Id_Area)) {
            abort(403, 'You cannot move records to areas you are not assigned to.');
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

        // ✅ Filter & merge dengan aman
        $areaMembers = array_filter((array) $request->input('selected_members_area', []));
        $allMembers = array_filter((array) $request->input('selected_members_all', []));
        $selectedNiks = array_values(array_unique(array_merge($areaMembers, $allMembers)));

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

        // ✅ PERBAIKAN UTAMA: gunakan 'Applied_Members' (sesuai $fillable)
        $penanganan->update([
            'Hour_Penanganan' => round($totalHour, 2),
            'Keterangan_Penanganan' => $keterangan,
            'Start_Penanganan' => $timestamp,
            'Id_Area' => $request->Id_Area,
            'Applied_Members' => $selectedNiks, // ← INI YANG BENAR!
        ]);

        return redirect()->back()->with('success', 'Time handling berhasil diperbarui.');
    }
    public function destroyPenanganan(Penanganan $penanganan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ SECURITY: Verify user area assignment
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));
        if (!$user->areas->contains('Id_Area', $penanganan->Id_Area)) {
            abort(403, 'You cannot delete records from areas you are not assigned to.');
        }

        $penanganan->delete();
        return redirect()->back()->with('success', 'Time handling berhasil dihapus.');
    }

    // 🔥 METHOD BARU: HAPUS SCAN
    // 🔥 METHOD BARU: HAPUS SCAN (di bagian bawah class)
    public function destroyScan(Request $request, Scan $scan)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        // ✅ PERBAIKAN: Gunakan relasi areas (MULTI-AREA)
        $user = \App\Models\User::with('areas')->findOrFail(session('Id_User'));

        if (!$user->areas->contains('Id_Area', $scan->Id_Area)) {
            abort(403, 'You can only delete scans from areas you are assigned to.');
        }

        $scan->delete();
        return redirect()->back()->with('success', 'Scan berhasil dihapus.');
    }

    public function exportReport(Request $request)
    {
        if (!session()->has('Id_User') || session('Id_Type_User') != 2) {
            abort(403);
        }

        $user = User::with('areas')->findOrFail(session('Id_User'));
        if ($user->areas->isEmpty()) {
            abort(403, 'Akun Anda belum ditugaskan ke area mana pun.');
        }

        // Use requested area or default to first assigned
        $activeAreaId = $request->query('area');
        $activeArea = null;
        if ($activeAreaId) {
            $activeArea = $user->areas->where('Id_Area', $activeAreaId)->first();
        }
        if (!$activeArea) {
            $activeArea = $user->areas->first();
        }
        $areaId = $activeArea->Id_Area;
        $areaName = $activeArea->Name_Area;

        $isMonthFilter = $request->filled('month');

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

        // === FETCH DATA (For assigned area only) ===
        $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
            ->where('Id_Area', $areaId)
            ->get();
        $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
            ->where('Id_Area', $areaId)
            ->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
            ->where('Id_Area', $areaId)
            ->get();
        $scans = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
            ->where('Id_Area', $areaId)
            ->with('tractor')->get();

        // === MEMBER NIK MAP ===
        $allNiks = Member::pluck('nama', 'nik')->toArray();

        // === BUILD SPREADSHEET ===
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("{$filePrefix} Report");

        // --- TITLE ---
        $sheet->setCellValue('A1', strtoupper($filePrefix) . ' PRODUCTION REPORT DATA');
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('A2', 'Area: ' . $areaName . ' | ' . ($isMonthFilter ? 'Bulan' : 'Tanggal') . ': ' . $dateLabel);
        $sheet->mergeCells('A2:E2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal('center');

        $row = 4;

        // ============================================================
        //  SECTION 1: NON OPERATIONAL COST
        // ============================================================
        $sec1Start = $row;
        $sheet->setCellValue("A{$row}", 'NON OPERATIONAL COST');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        // Header
        $headers = ['No', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E2F3');
        $row++;

        $no = 1;
        $costStartRow = $row;
        foreach ($costs as $cost) {
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $cost->Keterangan_Cost ?? '-');
            $sheet->setCellValue("C{$row}", round((float) $cost->Non_Operational_Cost, 2));
            $sheet->setCellValue("D{$row}", Carbon::parse($cost->Start_Cost)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $costEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'TOTAL NON OPERATIONAL');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        if ($costStartRow <= $costEndRow) {
            $sheet->setCellValue("C{$row}", "=SUM(C{$costStartRow}:C{$costEndRow})");
        } else {
            $sheet->setCellValue("C{$row}", 0);
        }
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');

        // Border Section 1
        $sheet->getStyle("A{$sec1Start}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row += 2;

        // ============================================================
        //  SECTION 2: ABSENSI
        // ============================================================
        $sec2Start = $row;
        $sheet->setCellValue("A{$row}", 'ABSENSI / IZIN');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF70AD47');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $headers2 = ['No', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers2 as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2EFDA');
        $row++;

        $no = 1;
        $powerStartRow = $row;
        foreach ($powers as $power) {
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $power->Keterangan_Power ?? '-');
            $sheet->setCellValue("C{$row}", round((float) $power->Leave_Hour_Power, 2));
            $sheet->setCellValue("D{$row}", Carbon::parse($power->Start_Power)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $powerEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'TOTAL ABSENSI');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        if ($powerStartRow <= $powerEndRow) {
            $sheet->setCellValue("C{$row}", "=SUM(C{$powerStartRow}:C{$powerEndRow})");
        } else {
            $sheet->setCellValue("C{$row}", 0);
        }
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');

        // Border Section 2
        $sheet->getStyle("A{$sec2Start}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row += 2;

        // ============================================================
        //  SECTION 3: PERBANTUAN / PENANGANAN
        // ============================================================
        $sec3Start = $row;
        $sheet->setCellValue("A{$row}", 'PERBANTUAN / PENANGANAN');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFED7D31');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $headers3 = ['No', 'Kategori', 'Jam (h)', 'Tanggal'];
        foreach ($headers3 as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFCE4D6');
        $row++;

        $no = 1;
        $penangananStartRow = $row;
        foreach ($penanganans as $p) {
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $p->Keterangan_Penanganan ?? '-');
            $sheet->setCellValue("C{$row}", round((float) $p->Hour_Penanganan, 2));
            $sheet->setCellValue("D{$row}", Carbon::parse($p->Start_Penanganan)->format('Y-m-d H:i'));
            $no++;
            $row++;
        }
        $penangananEndRow = $row - 1;

        // Total
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'TOTAL PERBANTUAN');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        if ($penangananStartRow <= $penangananEndRow) {
            $sheet->setCellValue("C{$row}", "=SUM(C{$penangananStartRow}:C{$penangananEndRow})");
        } else {
            $sheet->setCellValue("C{$row}", 0);
        }
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');

        // Border Section 3
        $sheet->getStyle("A{$sec3Start}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row += 2;

        // ============================================================
        //  SECTION 4: SCAN TRAKTOR (TOTAL SAJA)
        // ============================================================
        $sec4Start = $row;
        $sheet->setCellValue("A{$row}", 'SCAN TRAKTOR');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF5B9BD5');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $sheet->setCellValue("A{$row}", 'Keterangan');
        $sheet->setCellValue("B{$row}", 'Total Jam (h)');
        $sheet->mergeCells("B{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
        $row++;

        $totalScanHours = $scans->sum('Assigned_Hour_Scan');
        $sheet->setCellValue("A{$row}", 'Total Jam Scan Traktor Keseluruhan');
        $sheet->setCellValue("B{$row}", round($totalScanHours, 2));
        $sheet->mergeCells("B{$row}:D{$row}");
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);

        // Border Section 4
        $sheet->getStyle("A{$sec4Start}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $row += 2;

        // ============================================================
        //  SECTION 5: RINGKASAN
        // ============================================================
        $sec5Start = $row;
        $sheet->setCellValue("A{$row}", 'RINGKASAN');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF7030A0');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $summaryHeaders = ['Area', 'Non Op (h)', 'Absensi (h)', 'Perbantuan (h)'];
        foreach ($summaryHeaders as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2D1F0');
        $row++;

        $areaCost = $costs->sum('Non_Operational_Cost');
        $areaPower = $powers->sum('Leave_Hour_Power');
        $areaPenanganan = $penanganans->sum('Hour_Penanganan');

        $sheet->setCellValue("A{$row}", $areaName);
        $sheet->setCellValue("B{$row}", round($areaCost, 2));
        $sheet->setCellValue("C{$row}", round($areaPower, 2));
        $sheet->setCellValue("D{$row}", round($areaPenanganan, 2));

        // Border Section 5
        $sheet->getStyle("A{$sec5Start}:D{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // === FORMATTING ===
        $lastRow = $row;

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(20);

        // Number format for jam columns
        $sheet->getStyle("C1:C{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Vertical center
        $sheet->getStyle("A1:D{$lastRow}")->getAlignment()->setVertical('center');
        $sheet->getStyle("A1:D{$lastRow}")->getAlignment()->setWrapText(true);

        // === DOWNLOAD ===
        $areaSuffix = str_replace(' ', '_', $areaName);
        $fileName = "{$filePrefix}_Report_Data_{$areaSuffix}_{$dateString}.xlsx";
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
