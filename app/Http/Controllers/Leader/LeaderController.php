<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Report;
use App\Models\DailyJob;
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Scan;
use App\Models\Area;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Style\Border;


class LeaderController extends Controller
{
    public function index(Request $request)
    {
        // 🔑 Ambil user yang login
        if (!session()->has('Id_User')) {
            return redirect()->route('login.form')->withErrors(['loginError' => 'Silakan login terlebih dahulu.']);
        }

        $userId = session('Id_User');
        $user = \App\Models\User::with('areas')->find($userId);

        if (!$user || $user->Id_Type_User != 2) {
            abort(403, 'Akses ditolak.');
        }

        // --- LOGIKA MULTI-AREA ---
        $assignedAreas = $user->areas;

        if ($assignedAreas->isEmpty() && $user->Id_Area) {
            $assignedAreas = Area::where('Id_Area', $user->Id_Area)->get();
        }

        if ($assignedAreas->isEmpty()) {
            abort(403, 'Akun Anda belum ditetapkan ke area produksi.');
        }

        // Tentukan Area Aktif
        $activeAreaid = $request->query('area');
        $activeArea = null;

        if ($activeAreaid) {
            $activeArea = $assignedAreas->where('Id_Area', $activeAreaid)->first();
        }

        if (!$activeArea) {
            $activeArea = $assignedAreas->first();
        }

        $area = $activeArea;
        $areaId = $area->Id_Area;

        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = \Carbon\Carbon::parse($request->month . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $isToday = false;
        } else {
            $date = $request->filled('date')
                ? \Carbon\Carbon::parse($request->date)->startOfDay()
                : \Carbon\Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();
        }

        // ✅ Build date-aware queries
        if ($isMonthFilter) {
            $scans = \App\Models\Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->with('tractor')->get();
            $costs = \App\Models\Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->get();
            $powers = \App\Models\Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->with('member')->get();
            $penanganans = \App\Models\Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->get();
        } else {
            $scans = \App\Models\Scan::whereDate('Time_Scan', $dateString)->where('Id_Area', $areaId)->with('tractor')->get();
            $costs = \App\Models\Cost::whereDate('Start_Cost', $dateString)->where('Id_Area', $areaId)->get();
            $powers = \App\Models\Power::whereDate('Start_Power', $dateString)->where('Id_Area', $areaId)->with('member')->get();
            $penanganans = \App\Models\Penanganan::whereDate('Start_Penanganan', $dateString)->where('Id_Area', $areaId)->get();
        }

        // ✅ Hitung member & jam member
        if ($isMonthFilter) {
            $reportMembers = 0;
            $memberHours = 0.0;
            $daysCounted = 0;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dayStr = $cursor->format('Y-m-d');
                $dayYmd = $cursor->format('Ymd');
                $dayReport = \App\Models\Report::where('Day_Report', $dayStr)->where('Id_Area', $areaId)->first();
                $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report
                    : \App\Models\DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $dayHours = $dayReport ? (float) $dayReport->Total_Hours_Report : ($dayMembers * 8.0);
                if ($dayMembers > 0) $daysCounted++;
                $reportMembers += $dayMembers;
                $memberHours += $dayHours;
                $cursor->addDay();
            }
            $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;
        } else {
            $productionDateYmd = $startDate->format('Ymd');
            $currentTotalMembers = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();

            $report = \App\Models\Report::where('Day_Report', $dateString)->where('Id_Area', $areaId)->first();
            $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

            if ($isToday) {
                $now = \Carbon\Carbon::now();
                $start = \Carbon\Carbon::today()->setTime(7, 30);
                $endOfWork = \Carbon\Carbon::today()->setTime(16, 0);
                if ($now->lt($start)) {
                    $memberHours = 0.0;
                } elseif ($now->gt($endOfWork)) {
                    $memberHours = $reportMembers * 8.0;
                } else {
                    $totalHours = $start->diffInRealSeconds($now) / 3600.0;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;
                    $totalHours = max(0, $totalHours);
                    $memberHours = $reportMembers * min($totalHours, 8.0);
                }
            } else {
                $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
            }
        }

        $powerTotal = $powers->sum('Leave_Hour_Power');

        // ✅ Siapkan data untuk JavaScript
        $scansForJs = $scans->map(function ($s) {
            return [
                'label' => $s->tractor?->Name_Tractor ?? 'Unknown',
                'value' => (float) $s->Assigned_Hour_Scan * (1 - 0.078),
            ];
        })->toArray();

        $costsForJs = $costs->map(function ($c) {
            return [
                'label' => $c->Keterangan_Cost ?? 'Unknown',
                'value' => (float) $c->Non_Operational_Cost
            ];
        })->toArray();

        $powersForJs = $powers->map(function ($p) {
            return [
                'label' => $p->Keterangan_Power ?? 'Unknown',
                'value' => (float) $p->Leave_Hour_Power
            ];
        })->toArray();

        $penanganansForJs = $penanganans->map(function ($p) {
            return [
                'label' => $p->Keterangan_Penanganan ?? 'Unknown',
                'value' => (float) $p->Hour_Penanganan
            ];
        })->toArray();

        $costImpactList = $costsForJs;

        $dashboardJsData = [
            'rawScans' => $scansForJs ?? [],
            'rawCosts' => $costsForJs ?? [],
            'rawPowers' => $powersForJs ?? [],
            'rawPenanganans' => $penanganansForJs ?? [],
            'memberHours' => (float) $memberHours,
            'reportMembers' => (int) $reportMembers,
            'powerTotal' => (float) $powerTotal,
        ];

        $filterMode = $isMonthFilter ? 'month' : 'date';

        return view('leaders.dashboard', compact(
            'scans',
            'costs',
            'memberHours',
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString',
            'isToday',
            'costImpactList',
            'area',
            'assignedAreas',
            'dashboardJsData',
            'filterMode'
        ));
    }
    public function fullscreen(Request $request)
    {
        // 🔑 1. Cek apakah user sudah login (via session manual)
        if (!session()->has('Id_User')) {
            return redirect()->route('login.form')->withErrors(['loginError' => 'Silakan login terlebih dahulu.']);
        }

        // 🔑 2. Pastikan user adalah LEADER (Id_Type_User == 2)
        if (session('Id_Type_User') != 2) {
            abort(403, 'Hanya leader yang diizinkan mengakses halaman ini.');
        }

        // 🔑 3. Ambil data user dari database berdasarkan Id_User di session
        $userId = session('Id_User');
        $user = \App\Models\User::with('areas')->find($userId);

        if (!$user) {
            session()->flush();
            return redirect()->route('login.form')->withErrors(['loginError' => 'Sesi tidak valid. Silakan login ulang.']);
        }

        $assignedAreas = $user->areas;

        // Fallback jika tidak ada areas di pivot, coba cek Id_Area lama
        if ($assignedAreas->isEmpty() && $user->Id_Area) {
            $assignedAreas = Area::where('Id_Area', $user->Id_Area)->get();
        }

        if ($assignedAreas->isEmpty()) {
            abort(403, 'Akun Anda belum ditetapkan ke area produksi.');
        }

        // Tentukan Area Aktif
        $activeAreaid = $request->query('area');
        $activeArea = null;

        if ($activeAreaid) {
            $activeArea = $assignedAreas->where('Id_Area', $activeAreaid)->first();
        }

        // Jika tidak ada area dipilih atau id area tidak valid untuk user ini, default ke yang pertama
        if (!$activeArea) {
            $activeArea = $assignedAreas->first();
        }

        $area = $activeArea;
        $areaId = $area->Id_Area;

        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = \Carbon\Carbon::parse($request->month . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $isToday = false;
        } else {
            $date = $request->filled('date')
                ? \Carbon\Carbon::parse($request->date)->startOfDay()
                : \Carbon\Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();
        }

        // ✅ Build date-aware queries
        if ($isMonthFilter) {
            $scans = \App\Models\Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->with('tractor')->get();
            $costs = \App\Models\Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->get();
            $powers = \App\Models\Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->with('member')->get();
            $penanganans = \App\Models\Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)->get();
        } else {
            $scans = \App\Models\Scan::whereDate('Time_Scan', $dateString)->where('Id_Area', $areaId)->with('tractor')->get();
            $costs = \App\Models\Cost::whereDate('Start_Cost', $dateString)->where('Id_Area', $areaId)->get();
            $powers = \App\Models\Power::whereDate('Start_Power', $dateString)->where('Id_Area', $areaId)->with('member')->get();
            $penanganans = \App\Models\Penanganan::whereDate('Start_Penanganan', $dateString)->where('Id_Area', $areaId)->get();
        }

        // ✅ Member hours
        if ($isMonthFilter) {
            $reportMembers = 0;
            $memberHours = 0.0;
            $daysCounted = 0;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dayStr = $cursor->format('Y-m-d');
                $dayYmd = $cursor->format('Ymd');
                $dayReport = \App\Models\Report::where('Day_Report', $dayStr)->where('Id_Area', $areaId)->first();
                $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report
                    : \App\Models\DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $dayHours = $dayReport ? (float) $dayReport->Total_Hours_Report : ($dayMembers * 8.0);
                if ($dayMembers > 0) $daysCounted++;
                $reportMembers += $dayMembers;
                $memberHours += $dayHours;
                $cursor->addDay();
            }
            $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;
        } else {
            $productionDateYmd = $startDate->format('Ymd');
            $currentTotalMembers = \App\Models\DailyJob::where('Production_Date_Plan', $productionDateYmd)
                ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
            $report = \App\Models\Report::where('Day_Report', $dateString)->where('Id_Area', $areaId)->first();
            $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

            if ($isToday) {
                $now = \Carbon\Carbon::now();
                $start = \Carbon\Carbon::today()->setTime(7, 30);
                $endOfWork = \Carbon\Carbon::today()->setTime(16, 0);
                if ($now->lt($start)) {
                    $memberHours = 0.0;
                } elseif ($now->gt($endOfWork)) {
                    $memberHours = $reportMembers * 8.0;
                } else {
                    $totalHours = $start->diffInRealSeconds($now) / 3600.0;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                    if ($now->gt(\Carbon\Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;
                    $totalHours = max(0, $totalHours);
                    $memberHours = $reportMembers * min($totalHours, 8.0);
                }
            } else {
                $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
            }
        }

        $powerTotal = $powers->sum('Leave_Hour_Power');
        $scanTotal = $scans->sum('Assigned_Hour_Scan');
        $costTotal = $costs->sum('Non_Operational_Cost');
        $penangananTotal = $penanganans->sum('Hour_Penanganan');
        $reportNetHours = $memberHours - $powerTotal;

        // ✅ Siapkan data untuk JavaScript
        $scansForJs = $scans->map(fn($s) => ['label' => $s->tractor?->Name_Tractor ?? 'Unknown', 'value' => (float) $s->Assigned_Hour_Scan * (1 - 0.078)])->toArray();
        $costsForJs = $costs->map(fn($c) => ['label' => $c->Keterangan_Cost ?? 'Unknown', 'value' => (float) $c->Non_Operational_Cost])->toArray();
        $powersForJs = $powers->map(fn($p) => ['label' => $p->Keterangan_Power ?? 'Unknown', 'value' => (float) $p->Leave_Hour_Power])->toArray();
        $penanganansForJs = $penanganans->map(fn($p) => ['label' => $p->Keterangan_Penanganan ?? 'Unknown', 'value' => (float) $p->Hour_Penanganan])->toArray();

        $dashboardJsData = [
            'rawScans' => $scansForJs ?? [],
            'rawCosts' => $costsForJs ?? [],
            'rawPowers' => $powersForJs ?? [],
            'rawPenanganans' => $penanganansForJs ?? [],
            'memberHours' => (float) $memberHours,
            'reportMembers' => (int) $reportMembers,
            'powerTotal' => (float) $powerTotal,
        ];

        $filterMode = $isMonthFilter ? 'month' : 'date';

        return view('leaders.dashboard-fullscreen', compact(
            'dateString',
            'isToday',
            'area',
            'assignedAreas',
            'memberHours',
            'reportMembers',
            'powerTotal',
            'reportNetHours',
            'scanTotal',
            'costTotal',
            'penangananTotal',
            'dashboardJsData',
            'filterMode'
        ));
    }

    private function formatHoursToText(float $totalHours): string
    {
        if ($totalHours <= 0) return '0 jam 0 menit';
        $hours = floor($totalHours);
        $minutes = round(($totalHours - $hours) * 60);
        if ($minutes >= 60) {
            $hours += floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return "{$hours} jam {$minutes} menit";
    }

    public function export(Request $request)
    {
        $userId = session('Id_User');
        $user = \App\Models\User::with('areas')->find($userId);

        if (!$user || $user->Id_Type_User != 2) {
            abort(403, 'Akses ditolak.');
        }

        $assignedAreas = $user->areas;

        if ($assignedAreas->isEmpty() && $user->Id_Area) {
            $assignedAreas = \App\Models\Area::where('Id_Area', $user->Id_Area)->get();
        }

        if ($assignedAreas->isEmpty()) {
            abort(403, 'Akun Anda belum ditetapkan ke area produksi.');
        }

        // Tentukan Area Aktif
        $activeAreaid = $request->query('area');
        $activeArea = null;

        if ($activeAreaid) {
            $activeArea = $assignedAreas->where('Id_Area', $activeAreaid)->first();
        }

        if (!$activeArea) {
            $activeArea = $assignedAreas->first();
        }

        $area = $activeArea;
        $areaId = $area->Id_Area;

        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthInput = $request->get('month');
            $monthParsed = Carbon::parse($monthInput . '-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $monthKey = $monthParsed->format('Y-m');

            // Ambil hari kerja dari work_days
            $workDay = \App\Models\WorkDay::where('Moth_Work_Day', $monthKey)->first();
            $totalWorkDays = $workDay ? (int) $workDay->Total_Work_Day : 0;

            if ($totalWorkDays <= 0) {
                return back()->with('error', "Hari kerja bulan {$monthKey} belum diisi. Silakan isi di menu Work Day terlebih dahulu.");
            }

            // === DATA QUERIES ===
            $scans = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('member', 'tractor')
                ->get();
            $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->get();
            $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->with('member')
                ->get();
            $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
                ->where('Id_Area', $areaId)
                ->get();

            // === MEMBER HOURS (monthly sum) ===
            $reportMembers = 0;
            $memberHours = 0.0;
            $daysCounted = 0;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dayStr = $cursor->format('Y-m-d');
                $dayYmd = $cursor->format('Ymd');
                $dayReport = Report::where('Day_Report', $dayStr)->where('Id_Area', $areaId)->first();
                $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report
                    : DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $dayHours = $dayReport ? (float) $dayReport->Total_Hours_Report : ($dayMembers * 8.0);
                if ($dayMembers > 0) $daysCounted++;
                $reportMembers += $dayMembers;
                $memberHours += $dayHours;
                $cursor->addDay();
            }
            $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;

            // === HITUNG KOMPONEN UTAMA ===
            $scanTotal = $scans->sum('Assigned_Hour_Scan');
            $nonOperationalTotal = $costs->sum('Non_Operational_Cost');

            // B9: Beban Produksi = nilai scan traktor murni
            $bebanProduksiTotal = $scanTotal;
            // B11: Kaizen = B9 × 0.078
            $kaizenTotal = $scanTotal * 0.078;

            $absensiTotal = $powers->sum('Leave_Hour_Power');
            $powerNetTotal = $memberHours - $absensiTotal;

            // === KATEGORI TETAP ===
            $fixedLabels = [
                'fix_back_up' => 'Fix Back Up Proses / 工程の応援',
                'back_up_absensi' => 'Back Up Absensi / 欠勤応援',
                'bantuan_pic' => 'Bantuan ke PIC Absensi / 欠勤対応の応援',
                'irregular' => 'Back Up Line Stop / Irregular / イレギュラー対応',
                'area_lain' => 'Perbantuan area lain / 他部署応援 【－】',
                'lembur_produksi' => 'Lembur Produksi / 生産残業',
                'lembur_mente' => 'Lembur Mente / メンテ残業',
            ];
            $handlingValues = array_fill_keys(array_keys($fixedLabels), 0.0);
            $manualEntries = [];

            foreach ($penanganans as $p) {
                $desc = $p->Keterangan_Penanganan;
                $hours = (float) $p->Hour_Penanganan;
                $descLower = strtolower($desc);
                $matched = false;

                if (str_contains($descLower, 'fix back up proses') || str_contains($desc, '工程の応援')) {
                    $handlingValues['fix_back_up'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'back up absensi') || str_contains($desc, '欠勤応援')) {
                    $handlingValues['back_up_absensi'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'bantuan ke pic absensi') || str_contains($desc, '欠勤対応の応援')) {
                    $handlingValues['bantuan_pic'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'back up line stop') || str_contains($desc, 'イレギュラー対応')) {
                    $handlingValues['irregular'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'perbantuan area lain') || str_contains($desc, '他部署応援')) {
                    $handlingValues['area_lain'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'lembur mente') || str_contains($desc, 'メンテ残業')) {
                    $handlingValues['lembur_mente'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'lembur') && !str_contains($descLower, 'mente')) {
                    $handlingValues['lembur_produksi'] += $hours;
                    $matched = true;
                }
                if (!$matched) {
                    $manualEntries[] = ['label' => $desc, 'hours' => $hours];
                }
            }

            $penangananCategories = [
                [$fixedLabels['fix_back_up'], $handlingValues['fix_back_up']],
                [$fixedLabels['back_up_absensi'], $handlingValues['back_up_absensi']],
                [$fixedLabels['bantuan_pic'], $handlingValues['bantuan_pic']],
                [$fixedLabels['irregular'], $handlingValues['irregular']],
                [$fixedLabels['area_lain'], $handlingValues['area_lain']],
                [$fixedLabels['lembur_produksi'], $handlingValues['lembur_produksi']],
                [$fixedLabels['lembur_mente'], $handlingValues['lembur_mente']],
            ];
            foreach ($manualEntries as $entry) {
                $penangananCategories[] = [$entry['label'], $entry['hours']];
            }

            // Prepend Penghematan placeholder (value set via formula later)
            array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', 0]);

            // ============ EXCEL ============
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Monthly Performance');

            $exportYear = $monthParsed->format('Y');
            $exportMonthName = $monthParsed->format('F Y');
            $sheet->setCellValue('A1', $exportYear . ' MONTHLY OPERATIONAL PERFORMANCE');
            $sheet->setCellValue('A2', $exportYear . '年の月次操業実績');
            $sheet->mergeCells('A1:C1');
            $sheet->mergeCells('A2:C2');
            $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');

            $sheet->setCellValue('A4', 'Bulan / 月');
            $sheet->setCellValue('B4', $exportMonthName);
            $sheet->mergeCells('B4:C4');
            $sheet->getStyle('A4:C4')->getFont()->setBold(true);
            $sheet->getStyle('A4:C4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // B5: Hari Kerja — dipakai sebagai referensi formula Man
            $sheet->setCellValue('A5', 'Hari Kerja / 稼働日数');
            $sheet->setCellValue('B5', $totalWorkDays);
            $sheet->mergeCells('B5:C5');
            $sheet->getStyle('B5')->getAlignment()->setHorizontal('left');
            $sheet->getStyle('A5:C5')->getFont()->setBold(true);
            $sheet->getStyle('A5:C5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension(4)->setRowHeight(20);
            $sheet->getRowDimension(5)->setRowHeight(20);

            $sheet->setCellValue('A6', 'Area / 部署');
            $sheet->setCellValue('B6', $area ? $area->Name_Area : 'ALL AREAS');
            $sheet->mergeCells('B6:C6');
            $sheet->getStyle('B6')->getAlignment()->setHorizontal('left');
            $sheet->getStyle('A6:C6')->getFont()->setBold(true);
            $sheet->getStyle('A6:C6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension(6)->setRowHeight(20);

            // Man formula: =B_row/8/$B$5 (jam ÷ 8 ÷ hari kerja)
            $manFormula = function (int $r): string {
                return "=B{$r}/8/\$B\$5";
            };

            $sheet->setCellValue('A7', 'Item・内容');
            $sheet->setCellValue('B7', 'Hour・時間');
            $sheet->setCellValue('C7', 'Man・人数');
            $sheet->getStyle('A7:C7')->getFont()->setBold(true);
            $sheet->getStyle('A7:C7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

            $row = 8;

            // ====== BEBAN ======
            $this->writeSectionHeader($sheet, $row++, 'Beban・負荷');

            $r_bebanProduksi = $row;
            $sheet->setCellValue("A$row", 'Beban Produksi・生産負荷');
            $sheet->setCellValue("B$row", $bebanProduksiTotal);
            $sheet->setCellValue("C$row", $manFormula($row));
            $row++;

            $r_nonOp = $row;
            $sheet->setCellValue("A$row", 'Non operational・生産外負荷');
            $sheet->setCellValue("B$row", $nonOperationalTotal);
            $sheet->setCellValue("C$row", $manFormula($row));
            $row++;

            $r_kaizen = $row;
            $sheet->setCellValue("A$row", 'Kaizen・過年度工数低減 (7.8%)');
            $sheet->setCellValue("B$row", "=-B{$r_bebanProduksi}*0.078");
            $sheet->setCellValue("C$row", $manFormula($row));
            $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FF0000FF');
            $row++;

            $r_partTitipan = $row;
            $sheet->setCellValue("A$row", 'Part Titipan・補修部品');
            $sheet->setCellValue("B$row", 0);
            $sheet->setCellValue("C$row", 0);
            $row++;

            $r_totalBeban = $row;
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_bebanProduksi}:B{$r_partTitipan})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_bebanProduksi}:C{$r_partTitipan})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            // ====== POWER ======
            $this->writeSectionHeader($sheet, $row++, 'Power・能力');

            $r_manPower = $row;
            $sheet->setCellValue("A$row", 'Man Power・能力');
            $sheet->setCellValue("B$row", $memberHours);
            $sheet->setCellValue("C$row", $manFormula($row));
            $row++;

            $r_absensi = $row;
            $sheet->setCellValue("A$row", 'Absensi・欠勤 (max 3%)');
            $sheet->setCellValue("B$row", -$absensiTotal);
            $sheet->setCellValue("C$row", $manFormula($row));
            $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FF0000FF');
            $row++;

            $r_totalPower = $row;
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_manPower}:B{$r_absensi})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_manPower}:C{$r_absensi})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0F0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            // ====== SELISIH ======
            $r_selisihA = $row;
            $sheet->setCellValue("A$row", 'Selisih A (Power-Beban)');
            $sheet->setCellValue("B$row", "=B{$r_totalPower}-B{$r_totalBeban}");
            $sheet->setCellValue("C$row", "=C{$r_totalPower}-C{$r_totalBeban}");
            $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
            $row++;

            // ====== PENANGANAN ======
            $this->writeSectionHeader($sheet, $row++, 'Penanganan / 对处 (max 10%)');

            $r_penghematan = $row; // B20: Penghematan
            $sheet->setCellValue("A$row", 'Penghematan Jam Bulan ini / 今月の工数低減');
            $sumHandlingStart = $row + 1;
            $sumHandlingEnd = $row + count($penangananCategories) - 1;
            $sheet->setCellValue("B$row", "=-(\$B\$$r_selisihA+SUM(B{$sumHandlingStart}:B{$sumHandlingEnd}))");
            $sheet->setCellValue("C$row", $manFormula($row));
            $row++;

            // Cetak kategori penanganan lainnya (B21 - B27+)
            $r_firstItem = $row;
            foreach (array_slice($penangananCategories, 1) as $cat) {
                $sheet->setCellValue("A$row", $cat[0]);
                $sheet->setCellValue("B$row", $cat[1]);
                $sheet->setCellValue("C$row", $manFormula($row));
                $row++;
            }
            $r_lastItem = $row - 1;

            $r_totalPenanganan = $row;
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_penghematan}:B{$r_lastItem})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_penghematan}:C{$r_lastItem})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0FFE0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            $r_selisihB = $row;
            $sheet->setCellValue("A$row", 'Selisih B (Selisih A + Penanganan)');
            $sheet->setCellValue("B$row", "=B{$r_selisihA}+B{$r_totalPenanganan}");
            $sheet->setCellValue("C$row", "=C{$r_selisihA}+C{$r_totalPenanganan}");
            $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
            $sheet->getStyle('B8:C' . ($row - 1))->getNumberFormat()->setFormatCode('0');
            $row++;

            // ====== PERSENTASE SUMMARY ======
            $row += 2;
            $sheet->setCellValue("A$row", "Presentase Efisiensi\n工数低減率");
            $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);

            $r_bebanBottom = $row + 3;
            $r_penghematanBottom = $row + 4;
            $r_powerBottom = $row + 5;
            $r_nonOpBottom = $row + 6;

            $sheet->setCellValue("B$row", "=B{$r_penghematanBottom}/B{$r_bebanBottom}");
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0%');
            $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
            $row++;

            $sheet->setCellValue("A$row", "Presentase Non Operational\n非稼働工数率");
            $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);
            $sheet->setCellValue("B$row", "=B{$r_nonOpBottom}/B{$r_powerBottom}");
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0%');
            $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
            $row++;

            // Summary Bottom data
            $row += 2;
            $sheet->setCellValue("A{$r_bebanBottom}", "Beban");
            $sheet->setCellValue("B{$r_bebanBottom}", "=C{$r_bebanProduksi}+C{$r_kaizen}+C{$r_partTitipan}");

            $sheet->setCellValue("A{$r_penghematanBottom}", "Penghematan");
            $sheet->setCellValue("B{$r_penghematanBottom}", "=C{$r_penghematan}");

            $p1_start = $r_firstItem;
            $p1_end = $r_firstItem + 3;
            $p2_start = $r_firstItem + 5;
            $p2_end = $r_lastItem;
            $sheet->setCellValue("A{$r_powerBottom}", "Power");
            $sheet->setCellValue("B{$r_powerBottom}", "=SUM(C{$p1_start}:C{$p1_end},C{$p2_start}:C{$p2_end})+C{$r_manPower}");

            $sheet->setCellValue("A{$r_nonOpBottom}", "Non Operational");
            $sheet->setCellValue("B{$r_nonOpBottom}", "=C{$r_nonOp}");

            $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getNumberFormat()->setFormatCode('0');
            $sheet->mergeCells("B{$r_bebanBottom}:C{$r_bebanBottom}");
            $sheet->mergeCells("B{$r_penghematanBottom}:C{$r_penghematanBottom}");
            $sheet->mergeCells("B{$r_powerBottom}:C{$r_powerBottom}");
            $sheet->mergeCells("B{$r_nonOpBottom}:C{$r_nonOpBottom}");
            $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getAlignment()->setHorizontal('right');

            $row = $r_nonOpBottom + 1;

            $sheet->getColumnDimension('A')->setWidth(40);
            $sheet->getColumnDimension('B')->setWidth(15);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
            $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '';
            $fileName = 'Monthly_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();

            $productionDateYmd = $startDate->format('Ymd');
            $allReports = Report::where('Day_Report', $dateString)->get()->keyBy('Id_Area');

            $report = $allReports->get($areaId);
            if ($report) {
                $reportMembers = (int) $report->Total_Member_Report;
                $sumHoursManual = (float) $report->Total_Hours_Report;
            } else {
                $reportMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $sumHoursManual = ($reportMembers * 8.0);
            }

            if ($isToday) {
                $now = Carbon::now();
                $start = Carbon::today()->setTime(7, 30);
                $endOfWork = Carbon::today()->setTime(16, 0);
                if ($now->lt($start)) {
                    $memberHours = 0.0;
                } elseif ($now->gt($endOfWork)) {
                    $memberHours = $reportMembers * 8.0;
                } else {
                    $totalHours = $start->diffInRealSeconds($now) / 3600.0;
                    if ($now->gt(Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                    if ($now->gt(Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                    if ($now->gt(Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;
                    $totalHours = max(0, $totalHours);
                    $memberHours = $reportMembers * min($totalHours, 8.0);
                }
            } else {
                $memberHours = $sumHoursManual;
            }

            // === TRANSACTIONS ===
            $scans = Scan::whereDate('Time_Scan', $dateString)->where('Id_Area', $areaId)->with('member', 'tractor')->get();
            $costs = Cost::whereDate('Start_Cost', $dateString)->where('Id_Area', $areaId)->get();
            $powers = Power::whereDate('Start_Power', $dateString)->where('Id_Area', $areaId)->with('member')->get();
            $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->where('Id_Area', $areaId)->get();

            // --- HITUNG KOMPONEN UTAMA ---
            $scanTotal = $scans->sum('Assigned_Hour_Scan');
            $nonOperationalTotal = $costs->sum('Non_Operational_Cost');

            // B9: Beban Produksi = nilai scan traktor murni (tanpa tambahan 7.8%)
            $bebanProduksiTotal = $scanTotal;
            // B11: Kaizen = B9 × 0.078 (ditampilkan sebagai negatif di Excel)
            $kaizenTotal = $scanTotal * 0.078;

            $absensiTotal = $powers->sum('Leave_Hour_Power');
            $powerNetTotal = $memberHours - $absensiTotal;

            // === KATEGORI TETAP ===
            $fixedLabels = [
                'fix_back_up' => 'Fix Back Up Proses / 工程の応援',
                'back_up_absensi' => 'Back Up Absensi / 欠勤応援',
                'bantuan_pic' => 'Bantuan ke PIC Absensi / 欠勤対応の応援',
                'irregular' => 'Back Up Line Stop / Irregular / イレギュラー対応',
                'area_lain' => 'Perbantuan area lain / 他部署応援 【－】',
                'lembur_produksi' => 'Lembur Produksi / 生産残業',
                'lembur_mente' => 'Lembur Mente / メンテ残業',
            ];
            $handlingValues = array_fill_keys(array_keys($fixedLabels), 0.0);
            $manualEntries = [];

            foreach ($penanganans as $p) {
                $desc = $p->Keterangan_Penanganan;
                $hours = (float) $p->Hour_Penanganan;
                $descLower = strtolower($desc);
                $matched = false;

                if (str_contains($descLower, 'fix back up proses') || str_contains($desc, '工程の応援')) {
                    $handlingValues['fix_back_up'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'back up absensi') || str_contains($desc, '欠勤応援')) {
                    $handlingValues['back_up_absensi'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'bantuan ke pic absensi') || str_contains($desc, '欠勤対応の応援')) {
                    $handlingValues['bantuan_pic'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'back up line stop') || str_contains($desc, 'イレギュラー対応')) {
                    $handlingValues['irregular'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'perbantuan area lain') || str_contains($desc, '他部署応援')) {
                    $handlingValues['area_lain'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'lembur mente') || str_contains($desc, 'メンテ残業')) {
                    $handlingValues['lembur_mente'] += $hours;
                    $matched = true;
                } elseif (str_contains($descLower, 'lembur') && !str_contains($descLower, 'mente')) {
                    $handlingValues['lembur_produksi'] += $hours;
                    $matched = true;
                }
                if (!$matched) {
                    $manualEntries[] = ['label' => $desc, 'hours' => $hours];
                }
            }

            $penangananCategories = [
                [$fixedLabels['fix_back_up'], $handlingValues['fix_back_up']],
                [$fixedLabels['back_up_absensi'], $handlingValues['back_up_absensi']],
                [$fixedLabels['bantuan_pic'], $handlingValues['bantuan_pic']],
                [$fixedLabels['irregular'], $handlingValues['irregular']],
                [$fixedLabels['area_lain'], $handlingValues['area_lain']],
                [$fixedLabels['lembur_produksi'], $handlingValues['lembur_produksi']],
                [$fixedLabels['lembur_mente'], $handlingValues['lembur_mente']],
            ];
            foreach ($manualEntries as $entry) {
                $penangananCategories[] = [$entry['label'], $entry['hours']];
            }

            // --- HITUNG PENANGANAN & PENGHEMATAN (LOGIKA PERSAMAAN) ---
            $penangananItems = array_column($penangananCategories, 1);
            $penangananSumWithoutPenghematan = array_sum($penangananItems); // SUM(B21:B27)

            // B13: Total Beban = SUM(B9:B12) = BebanProduksi + NonOp + (-Kaizen) + PartTitipan
            $totalBebanAkhir = $bebanProduksiTotal + $nonOperationalTotal - $kaizenTotal;

            // B18: Selisih A = Power Net (B17) - Total Beban (B13)
            $selisihA = $powerNetTotal - $totalBebanAkhir;

            // B20: Penghematan Jam (untuk membuat B29 = 0)
            $penghematanJam = - ($selisihA + $penangananSumWithoutPenghematan);

            // Sisipkan penghematan di posisi pertama (B20)
            array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', $penghematanJam]);

            // Rebuild items setelah prepend penghematan
            $penangananItems = array_column($penangananCategories, 1);
            $penangananTotal = array_sum($penangananItems);

            // B29: Selisih B = B18 + B28
            $selisihB = $selisihA + $penangananTotal;

            // --- Konversi ke Man (÷8) ---
            $hoursToMan = fn(float $h): float => $h / 8;

            $manBebanProduksi = $hoursToMan($bebanProduksiTotal);
            $manNonOperational = $hoursToMan($nonOperationalTotal);
            $manKaizen = $hoursToMan($kaizenTotal);
            $manTotalBeban = $hoursToMan($totalBebanAkhir);
            $manAbsensi = $hoursToMan($absensiTotal);
            $manPowerNet = $memberHours / 8 - $manAbsensi;
            $manSelisihA = $hoursToMan($selisihA);
            $manPenangananItems = array_map($hoursToMan, $penangananItems);
            $manPenangananTotal = array_sum($manPenangananItems);
            $manSelisihB = $hoursToMan($selisihB);

            // Efisiensi: berdasarkan totalBebanAkhir (B13)
            $efisiensiPersen = $totalBebanAkhir > 0 ? (($totalBebanAkhir - $powerNetTotal) / $totalBebanAkhir) * 100 : 0;
            $nonOperationalPersen = $totalBebanAkhir > 0 ? ($nonOperationalTotal / $totalBebanAkhir) * 100 : 0;

            // --- EXCEL ---
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Operational Performance');

            $exportYear = Carbon::parse($dateString)->format('Y');
            $sheet->setCellValue('A1', $exportYear . ' OPERATIONAL PERFORMANCE');
            $sheet->setCellValue('A2', $exportYear . '年の操業実績');
            $sheet->mergeCells('A1:C1');
            $sheet->mergeCells('A2:C2');
            $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');

            $sheet->setCellValue('A4', 'Tanggal / 日付');
            $sheet->setCellValue('B4', Carbon::parse($dateString)->format('d F Y'));
            $sheet->mergeCells('B4:C4');
            $sheet->getStyle('A4:C4')->getFont()->setBold(true);
            $sheet->getStyle('A4:C4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension(4)->setRowHeight(20);

            $sheet->setCellValue('A5', 'Area / 部署');
            $sheet->setCellValue('B5', $area ? $area->Name_Area : 'ALL AREAS');
            $sheet->mergeCells('B5:C5');
            $sheet->getStyle('A5:C5')->getFont()->setBold(true);
            $sheet->getStyle('A5:C5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension(5)->setRowHeight(20);

            $sheet->setCellValue('A7', 'Item・内容');
            $sheet->setCellValue('B7', 'Hour・時間');
            $sheet->setCellValue('C7', 'Man・人数');
            $sheet->getStyle('A7:C7')->getFont()->setBold(true);
            $sheet->getStyle('A7:C7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

            $row = 8;

            // ====== BEBAN (row 8-13) ======
            $this->writeSectionHeader($sheet, $row++, 'Beban・負荷');              // row 8: header
            $r_bebanProduksi = $row;                                              // row 9
            $sheet->setCellValue("A$row", 'Beban Produksi・生産負荷');
            $sheet->setCellValue("B$row", $bebanProduksiTotal);
            $sheet->setCellValue("C$row", "=B$row/8");
            $row++;

            $r_nonOp = $row;                                                     // row 10
            $sheet->setCellValue("A$row", 'Non operational・生産外負荷');
            $sheet->setCellValue("B$row", $nonOperationalTotal);
            $sheet->setCellValue("C$row", "=B$row/8");
            $row++;

            $r_kaizen = $row;                                                    // row 11
            $sheet->setCellValue("A$row", 'Kaizen・過年度工数低減 (7.8%)');
            $sheet->setCellValue("B$row", "=-B{$r_bebanProduksi}*0.078");
            $sheet->setCellValue("C$row", "=B$row/8");
            $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FF0000FF');
            $row++;

            $r_partTitipan = $row;                                               // row 12
            $sheet->setCellValue("A$row", 'Part Titipan・補修部品');
            $sheet->setCellValue("B$row", 0);
            $sheet->setCellValue("C$row", 0);
            $row++;

            $r_totalBeban = $row;                                                // row 13
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_bebanProduksi}:B{$r_partTitipan})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_bebanProduksi}:C{$r_partTitipan})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            // ====== POWER (row 14-18) ======
            $this->writeSectionHeader($sheet, $row++, 'Power・能力');              // row 14: header

            $r_manPower = $row;                                                  // row 15
            $sheet->setCellValue("A$row", 'Man Power・能力');
            $sheet->setCellValue("B$row", $memberHours);
            $sheet->setCellValue("C$row", "=B$row/8");
            $row++;

            $r_absensi = $row;                                                   // row 16
            $sheet->setCellValue("A$row", 'Absensi・欠勤 (max 3%)');
            $sheet->setCellValue("B$row", -$absensiTotal);
            $sheet->setCellValue("C$row", "=B$row/8");
            $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FF0000FF');
            $row++;

            $r_totalPower = $row;                                                // row 17
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_manPower}:B{$r_absensi})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_manPower}:C{$r_absensi})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0F0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            $r_selisihA = $row;                                                  // row 18
            $sheet->setCellValue("A$row", 'Selisih A (Power-Beban)');
            $sheet->setCellValue("B$row", "=B{$r_totalPower}-B{$r_totalBeban}");
            $sheet->setCellValue("C$row", "=C{$r_totalPower}-C{$r_totalBeban}");
            $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
            $row++;

            // ====== PENANGANAN (row 19-28) ======
            $this->writeSectionHeader($sheet, $row++, 'Penanganan / 对处 (max 10%)'); // row 19: header

            $r_penghematan = $row;                                               // row 20
            $sheet->setCellValue("A$row", 'Penghematan Jam Bulan ini / 今月の工数低減');
            $sumHandlingStart = $row + 1;
            $sumHandlingEnd = $row + count($penangananCategories) - 1;
            $sheet->setCellValue("B$row", "=-(\$B\$$r_selisihA+SUM(B{$sumHandlingStart}:B{$sumHandlingEnd}))");
            $sheet->setCellValue("C$row", "=B$row/8");
            $row++;

            $r_firstItem = $row;
            foreach (array_slice($penangananCategories, 1) as $cat) {
                $sheet->setCellValue("A$row", $cat[0]);
                $sheet->setCellValue("B$row", $cat[1]);
                $sheet->setCellValue("C$row", "=B$row/8");
                $row++;
            }
            $r_lastItem = $row - 1;

            $r_totalPenanganan = $row;                                           // row 28
            $sheet->setCellValue("A$row", 'Total・計');
            $sheet->setCellValue("B$row", "=SUM(B{$r_penghematan}:B{$r_lastItem})");
            $sheet->setCellValue("C$row", "=SUM(C{$r_penghematan}:C{$r_lastItem})");
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0FFE0');
            $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
            $row++;

            $r_selisihB = $row;                                                  // row 29
            $sheet->setCellValue("A$row", 'Selisih B (Selisih A + Penanganan)');
            $sheet->setCellValue("B$row", "=B{$r_selisihA}+B{$r_totalPenanganan}");
            $sheet->setCellValue("C$row", "=C{$r_selisihA}+C{$r_totalPenanganan}");
            $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
            $sheet->getStyle('B8:C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
            $row++;

            // ====== PERSENTASE SUMMARY ======
            $row += 2;
            $sheet->setCellValue("A$row", "Presentase Efisiensi\n工数低減率");
            $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);

            $r_bebanBottom = $row + 3;
            $r_penghematanBottom = $row + 4;
            $r_powerBottom = $row + 5;
            $r_nonOpBottom = $row + 6;

            $sheet->setCellValue("B$row", "=B{$r_penghematanBottom}/B{$r_bebanBottom}");
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0%');
            $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
            $row++;

            $sheet->setCellValue("A$row", "Presentase Non Operational\n非稼働工数率");
            $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);
            $sheet->setCellValue("B$row", "=B{$r_nonOpBottom}/B{$r_powerBottom}");
            $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0%');
            $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
            $row++;

            // Summary Bottom
            $row += 2;
            $sheet->setCellValue("A{$r_bebanBottom}", "Beban");
            $sheet->setCellValue("B{$r_bebanBottom}", "=C{$r_bebanProduksi}+C{$r_kaizen}+C{$r_partTitipan}");

            // A38 Penghematan (hilangkan C38 sesuai request)
            $sheet->setCellValue("A{$r_penghematanBottom}", "Penghematan");
            $sheet->setCellValue("B{$r_penghematanBottom}", "=C{$r_penghematan}");

            // A39 Power
            $p1_start = $r_firstItem;
            $p1_end = $r_firstItem + 3;
            $p2_start = $r_firstItem + 5;
            $p2_end = $r_lastItem;
            $sheet->setCellValue("A{$r_powerBottom}", "Power");
            $sheet->setCellValue("B{$r_powerBottom}", "=SUM(C{$p1_start}:C{$p1_end},C{$p2_start}:C{$p2_end})+C{$r_manPower}");

            // A40 Non Operational
            $sheet->setCellValue("A{$r_nonOpBottom}", "Non Operational");
            $sheet->setCellValue("B{$r_nonOpBottom}", "=C{$r_nonOp}");

            $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->mergeCells("B{$r_bebanBottom}:C{$r_bebanBottom}");
            $sheet->mergeCells("B{$r_penghematanBottom}:C{$r_penghematanBottom}");
            $sheet->mergeCells("B{$r_powerBottom}:C{$r_powerBottom}");
            $sheet->mergeCells("B{$r_nonOpBottom}:C{$r_nonOpBottom}");
            $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getAlignment()->setHorizontal('right');

            $row = $r_nonOpBottom + 1;

            $sheet->getColumnDimension('A')->setWidth(40);
            $sheet->getColumnDimension('B')->setWidth(15);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
            $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '';
            $fileName = 'Operational_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }
    }

    // --- HELPER EXCEL (SEMUA SUDAH DITAMBAHKAN) ---

    private function writeSectionHeader($sheet, int $row, string $text): void
    {
        $sheet->setCellValue("A$row", $text);
        $sheet->getStyle("A$row")->getFont()->setBold(true);
        $sheet->mergeCells("A$row:C$row");
    }

    private function writeRow($sheet, int $row, string $label, $hours, $man): void
    {
        $sheet->setCellValue("A$row", $label);
        $sheet->setCellValue("B$row", $hours);
        $sheet->setCellValue("C$row", $man);
    }

    private function writeRowColored($sheet, int $row, string $label, $hours, $man, string $color): void
    {
        $this->writeRow($sheet, $row, $label, $hours, $man);
        $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB($color);
    }

    private function writeRowWithBackground($sheet, int $row, string $label, $hours, $man, ?string $bgColor = null): void
    {
        $this->writeRow($sheet, $row, $label, $hours, $man);
        if ($bgColor) {
            $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($bgColor);
        }
    }

    private function writeTotalRow($sheet, int $row, string $label, $hours, $man, string $bgColor): void
    {
        $this->writeRow($sheet, $row, $label, $hours, $man);
        $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($bgColor);
        $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
    }

    // 🔸 Perbaikan: ubah $man dari `int` jadi `float`
    private function writeDifferenceRow($sheet, int $row, string $label, float $hours, float $man): void
    {
        $hoursDisplay = $hours < 0 ? "▲" . abs($hours) : $hours;
        $manDisplay = $man < 0 ? "▲" . abs($man) : $man;
        $color = $hours < 0 ? 'FF0000FF' : 'FF000000';

        $sheet->setCellValue("A$row", $label);
        $sheet->setCellValue("B$row", $hoursDisplay);
        $sheet->setCellValue("C$row", $manDisplay);
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB($color);
    }
}
