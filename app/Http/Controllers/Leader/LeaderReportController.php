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

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today()->startOfDay();

        $dateString = $date->format('Y-m-d');
        $productionDateYmd = $date->format('Ymd');

        // Hanya ambil data untuk area leader ini
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

        // Active members di area ini hari ini
        $dailyJobNiks = DailyJob::where('Production_Date_Plan', $productionDateYmd)
            ->where('Id_Area', $areaId)
            ->pluck('Nik_Daily_Job')
            ->unique();
        $activeMembers = Member::whereIn('nik', $dailyJobNiks)->get();
        $activeMembersByArea = [$areaId => $activeMembers];

        // 🔥 Ambil SEMUA member dari semua area (untuk dropdown "All Areas")
        $allMembers = Member::with('area')->get();

        // 🔥 Ambil scan: filter by date
        $scans = Scan::where('Id_Area', $areaId)
            ->whereDate('Time_Scan', $date->format('Y-m-d'))
            ->with(['tractor', 'dailyJob'])
            ->orderBy('Time_Scan', 'desc')
            ->get();

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
}
