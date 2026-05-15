<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cost;
use App\Models\DailyJob;
use App\Models\Penanganan;
use App\Models\Power;
use App\Models\Report;
use App\Models\Scan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month.'-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $isToday = false;
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();
        }

        $areas = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
        $areaId = $request->query('area');

        // ✅ Build date-aware queries
        if ($isMonthFilter) {
            $scanQuery = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))->with('tractor');
            $costQuery = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'));
            $powerQuery = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))->with('member');
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'));
        } else {
            $scanQuery = Scan::whereDate('Time_Scan', $dateString)->with('tractor');
            $costQuery = Cost::whereDate('Start_Cost', $dateString);
            $powerQuery = Power::whereDate('Start_Power', $dateString)->with('member');
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', $dateString);
        }

        if ($areaId) {
            $scanQuery->where('Id_Area', $areaId);
            $costQuery->where('Id_Area', $areaId);
            $powerQuery->where('Id_Area', $areaId);
            $penanganansQuery->where('Id_Area', $areaId);
        }

        $scans = $scanQuery->get();
        $costs = $costQuery->get();
        $powers = $powerQuery->get();
        $penanganans = $penanganansQuery->get();

        $costImpactList = $costs->map(function ($cost) {
            return [
                'label' => $cost->Keterangan_Cost ?? 'Unknown',
                'value' => (float) $cost->Non_Operational_Cost,
            ];
        })->toArray();

        $powerTotal = $powers->sum('Leave_Hour_Power');

        // ✅ Hitung member & jam member
        if ($isMonthFilter) {
            // Monthly: loop each day in month, sum reports or fallback
            $reportMembers = 0;
            $memberHours = 0.0;
            $daysCounted = 0;

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dayStr = $cursor->format('Y-m-d');
                $dayYmd = $cursor->format('Ymd');
                $dayReports = Report::where('Day_Report', $dayStr)->get()->keyBy('Id_Area');

                if ($areaId) {
                    $dayReport = $dayReports->get($areaId);
                    $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report
                        : DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                    $dayHours = $dayReport ? (float) $dayReport->Total_Hours_Report : ($dayMembers * 8.0);
                } else {
                    $dayMembers = 0;
                    $dayHours = 0;
                    foreach ($areas as $area) {
                        $areaReport = $dayReports->get($area->Id_Area);
                        if ($areaReport) {
                            $dayMembers += (int) $areaReport->Total_Member_Report;
                            $dayHours += (float) $areaReport->Total_Hours_Report;
                        } else {
                            $ac = DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                            $dayMembers += $ac;
                            $dayHours += ($ac * 8.0);
                        }
                    }
                }

                if ($dayMembers > 0) {
                    $daysCounted++;
                }
                $reportMembers += $dayMembers;
                $memberHours += $dayHours;
                $cursor->addDay();
            }

            // Average members for display
            $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;
        } else {
            // Daily mode (original logic)
            $productionDateYmd = $startDate->format('Ymd');
            $allReports = Report::where('Day_Report', $dateString)->get()->keyBy('Id_Area');

            if ($areaId) {
                $currentTotalMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $report = $allReports->get($areaId);
                $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;
            } else {
                $sumMembers = 0;
                foreach ($areas as $area) {
                    $areaReport = $allReports->get($area->Id_Area);
                    if ($areaReport) {
                        $sumMembers += (int) $areaReport->Total_Member_Report;
                    } else {
                        $sumMembers += DailyJob::where('Production_Date_Plan', $productionDateYmd)
                            ->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                    }
                }
                $reportMembers = $sumMembers;
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
                    if ($now->gt(Carbon::today()->setTime(10, 0))) {
                        $totalHours -= 10 / 60;
                    }
                    if ($now->gt(Carbon::today()->setTime(12, 0))) {
                        $totalHours -= 40 / 60;
                    }
                    if ($now->gt(Carbon::today()->setTime(15, 0))) {
                        $totalHours -= 10 / 60;
                    }
                    $totalHours = max(0, $totalHours);
                    $memberHours = $reportMembers * min($totalHours, 8.0);
                }
            } else {
                if ($areaId) {
                    $report = $allReports->get($areaId);
                    $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
                } else {
                    $sumHours = 0;
                    foreach ($areas as $area) {
                        $areaReport = $allReports->get($area->Id_Area);
                        if ($areaReport) {
                            $sumHours += (float) $areaReport->Total_Hours_Report;
                        } else {
                            $areaCount = DailyJob::where('Production_Date_Plan', $startDate->format('Ymd'))
                                ->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                            $sumHours += ($areaCount * 8.0);
                        }
                    }
                    $memberHours = $sumHours;
                }
            }
        }

        $memberHoursText = $this->formatHoursToText($memberHours);

        // ✅ Siapkan data untuk JavaScript
        $scansForJs = $scans->map(function ($s) {
            return [
                'label' => $s->tractor?->Name_Tractor ?? 'Unknown',
                'value' => (float) $s->Assigned_Hour_Scan * (1 - 0.078),
            ];
        })->toArray();

        $powersForJs = $powers->map(function ($p) {
            return [
                'label' => $p->Keterangan_Power ?? 'Unknown',
                'value' => (float) $p->Leave_Hour_Power,
            ];
        })->toArray();

        $penanganansForJs = $penanganans->map(function ($p) {
            return [
                'label' => $p->Keterangan_Penanganan ?? 'Unknown',
                'value' => (float) $p->Hour_Penanganan,
            ];
        })->toArray();

        $dashboardJsData = [
            'rawScans' => $scansForJs ?? [],
            'rawCosts' => $costImpactList ?? [],
            'rawPowers' => $powersForJs ?? [],
            'rawPenanganans' => $penanganansForJs ?? [],
            'memberHours' => (float) $memberHours,
            'reportMembers' => (int) $reportMembers,
            'powerTotal' => (float) $powerTotal,
        ];

        $filterMode = $isMonthFilter ? 'month' : 'date';

        return view('admins.dashboard', compact(
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
            'areas',
            'areaId',
            'dashboardJsData',
            'filterMode'
        ));
    }

    public function fullscreen(Request $request)
    {
        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month.'-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $isToday = false;
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();
        }

        $areas = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
        $areaData = [];

        foreach ($areas as $area) {
            $areaId = $area->Id_Area;

            // Ambil data with date-range support
            if ($isMonthFilter) {
                $scans = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                    ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))
                    ->where('Id_Area', $areaId)->with('tractor')->get();
                $costs = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                    ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'))
                    ->where('Id_Area', $areaId)->get();
                $powers = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                    ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))
                    ->where('Id_Area', $areaId)->with('member')->get();
                $penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                    ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'))
                    ->where('Id_Area', $areaId)->get();

                // Monthly member hours: sum across each day
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
                    if ($dayMembers > 0) {
                        $daysCounted++;
                    }
                    $reportMembers += $dayMembers;
                    $memberHours += $dayHours;
                    $cursor->addDay();
                }
                $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;
            } else {
                $productionDateYmd = $startDate->format('Ymd');
                $currentTotalMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();

                $scans = Scan::whereDate('Time_Scan', $dateString)->where('Id_Area', $areaId)->with('tractor')->get();
                $costs = Cost::whereDate('Start_Cost', $dateString)->where('Id_Area', $areaId)->get();
                $powers = Power::whereDate('Start_Power', $dateString)->where('Id_Area', $areaId)->with('member')->get();
                $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->where('Id_Area', $areaId)->get();

                $report = Report::where('Day_Report', $dateString)->where('Id_Area', $areaId)->first();
                $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

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
                        if ($now->gt(Carbon::today()->setTime(10, 0))) {
                            $totalHours -= 10 / 60;
                        }
                        if ($now->gt(Carbon::today()->setTime(12, 0))) {
                            $totalHours -= 40 / 60;
                        }
                        if ($now->gt(Carbon::today()->setTime(15, 0))) {
                            $totalHours -= 10 / 60;
                        }
                        $totalHours = max(0, $totalHours);
                        $memberHours = $reportMembers * min($totalHours, 8.0);
                    }
                } else {
                    $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
                }
            }

            $scanTotal = $scans->sum('Assigned_Hour_Scan');
            $costTotal = $costs->sum('Non_Operational_Cost');
            $penangananTotal = $penanganans->sum('Hour_Penanganan');
            $powerTotal = $powers->sum('Leave_Hour_Power');
            $reportNetHours = $memberHours - $powerTotal;
            $kategori1 = $reportNetHours + $penangananTotal;
            $kategori2 = $scanTotal + $costTotal;
            $selisihJam = $kategori2 - $kategori1;
            $nilaiRupiah = $selisihJam * 60000;

            $areaData[] = [
                'area' => $area,
                'reportMembers' => $reportMembers,
                'memberHours' => $memberHours,
                'reportNetHours' => $reportNetHours,
                'scanTotal' => $scanTotal,
                'costTotal' => $costTotal,
                'penangananTotal' => $penangananTotal,
                'powerTotal' => $powerTotal,
                'selisihJam' => $selisihJam,
                'nilaiRupiah' => $nilaiRupiah,
            ];
        }

        // Build chart data as simple array for JSON
        $chartDataJson = [];
        foreach ($areaData as $d) {
            $chartDataJson[] = [
                'id' => $d['area']->Id_Area,
                'reportNetHours' => max(0, $d['reportNetHours']),
                'penangananTotal' => $d['penangananTotal'],
                'scanTotal' => $d['scanTotal'] * (1 - 0.078),
                'costTotal' => $d['costTotal'],
            ];
        }

        $filterMode = $isMonthFilter ? 'month' : 'date';

        return view('admins.dashboard-fullscreen', compact('dateString', 'isToday', 'areaData', 'chartDataJson', 'filterMode'));
    }

    private function formatHoursToText(float $totalHours): string
    {
        if ($totalHours <= 0) {
            return '0 jam 0 menit';
        }
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
        // ✅ Determine filter mode: month or date
        $isMonthFilter = $request->filled('month');

        if ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month.'-01');
            $startDate = $monthParsed->copy()->startOfMonth();
            $endDate = $monthParsed->copy()->endOfMonth();
            $dateString = $monthParsed->format('Y-m');
            $isToday = false;
        } else {
            $date = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today();
            $startDate = $date->copy();
            $endDate = $date->copy();
            $dateString = $date->format('Y-m-d');
            $isToday = $date->isToday();
        }

        $areaId = $request->query('area');
        $areas = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        if ($isMonthFilter) {
            $scanQuery = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))->with('member', 'tractor');
            $costQuery = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'));
            $powerQuery = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))->with('member');
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
                ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'));
        } else {
            $scanQuery = Scan::whereDate('Time_Scan', $dateString)->with('member', 'tractor');
            $costQuery = Cost::whereDate('Start_Cost', $dateString);
            $powerQuery = Power::whereDate('Start_Power', $dateString)->with('member');
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', $dateString);
        }

        if ($areaId) {
            $scanQuery->where('Id_Area', $areaId);
            $costQuery->where('Id_Area', $areaId);
            $powerQuery->where('Id_Area', $areaId);
            $penanganansQuery->where('Id_Area', $areaId);
        }

        $scans = $scanQuery->get();
        $costs = $costQuery->get();
        $powers = $powerQuery->get();
        $penanganans = $penanganansQuery->get();

        if ($isMonthFilter) {
            $reportMembers = 0;
            $memberHours = 0.0;
            $daysCounted = 0;
            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                $dayStr = $cursor->format('Y-m-d');
                $dayYmd = $cursor->format('Ymd');
                $dayReports = Report::where('Day_Report', $dayStr)->get()->keyBy('Id_Area');
                if ($areaId) {
                    $dayReport = $dayReports->get($areaId);
                    $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report
                        : DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                    $dayHours = $dayReport ? (float) $dayReport->Total_Hours_Report : ($dayMembers * 8.0);
                } else {
                    $dayMembers = 0;
                    $dayHours = 0;
                    foreach ($areas as $area) {
                        $areaReport = $dayReports->get($area->Id_Area);
                        if ($areaReport) {
                            $dayMembers += (int) $areaReport->Total_Member_Report;
                            $dayHours += (float) $areaReport->Total_Hours_Report;
                        } else {
                            $ac = DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                            $dayMembers += $ac;
                            $dayHours += ($ac * 8.0);
                        }
                    }
                }
                if ($dayMembers > 0) {
                    $daysCounted++;
                }
                $reportMembers += $dayMembers;
                $memberHours += $dayHours;
                $cursor->addDay();
            }
            $reportMembers = $daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0;
        } else {
            $productionDateYmd = $startDate->format('Ymd');
            $allReports = Report::where('Day_Report', $dateString)->get()->keyBy('Id_Area');

            if ($areaId) {
                $report = $allReports->get($areaId);
                if ($report) {
                    $reportMembers = (int) $report->Total_Member_Report;
                    $sumHoursManual = (float) $report->Total_Hours_Report;
                } else {
                    $reportMembers = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                        ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                    $sumHoursManual = ($reportMembers * 8.0);
                }
            } else {
                $sumMembers = 0;
                $sumHoursManual = 0;
                foreach ($areas as $area) {
                    $areaReport = $allReports->get($area->Id_Area);
                    if ($areaReport) {
                        $sumMembers += (int) $areaReport->Total_Member_Report;
                        $sumHoursManual += (float) $areaReport->Total_Hours_Report;
                    } else {
                        $areaCount = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                            ->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                        $sumMembers += $areaCount;
                        $sumHoursManual += ($areaCount * 8.0);
                    }
                }
                $reportMembers = $sumMembers;
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
                    if ($now->gt(Carbon::today()->setTime(10, 0))) {
                        $totalHours -= 10 / 60;
                    }
                    if ($now->gt(Carbon::today()->setTime(12, 0))) {
                        $totalHours -= 40 / 60;
                    }
                    if ($now->gt(Carbon::today()->setTime(15, 0))) {
                        $totalHours -= 10 / 60;
                    }
                    $totalHours = max(0, $totalHours);
                    $memberHours = $reportMembers * min($totalHours, 8.0);
                }
            } else {
                $memberHours = $sumHoursManual;
            }
        }

        // --- HITUNG KOMPONEN UTAMA ---
        $scanTotal = $scans->sum('Assigned_Hour_Scan');
        $nonOperationalTotal = $costs->sum('Non_Operational_Cost');

        // ✅ Perbaikan utama sesuai permintaan:
        $kaizenTotal = $scanTotal; // Kaizen = scan total
        $bebanProduksiTotal = $scanTotal + ($scanTotal * 0.078); // Beban = scan + 7.8%

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
            } elseif (str_contains($descLower, 'lembur') && ! str_contains($descLower, 'mente')) {
                $handlingValues['lembur_produksi'] += $hours;
                $matched = true;
            }

            if (! $matched) {
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

        $penangananItems = array_column($penangananCategories, 1);
        $penangananTotal = array_sum($penangananItems);

        // Penghematan: sesuaikan dengan logika baru (NonOp tetap dihitung di sini untuk penghematan)
        $penghematanJam = ($scanTotal + $nonOperationalTotal) - ($powerNetTotal + $penangananTotal);

        array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', $penghematanJam]);
        array_unshift($penangananItems, $penghematanJam);

        // --- Konversi ke Man ---
        $hoursToMan = fn (float $h): float => $h / 8;

        // ✅ Perbaikan: manBebanProduksi dihitung dari bebanProduksiTotal, bukan scanTotal
        $manBebanProduksi = $hoursToMan($bebanProduksiTotal);
        $manNonOperational = $hoursToMan($nonOperationalTotal);
        $manKaizen = $hoursToMan($kaizenTotal);
        // ✅ Total beban = bebanProduksiTotal + nonOperational (jika tetap ingin tampilkan total gabungan)
        // Tapi sesuai permintaan: "beban produksi = scan + 7.8%", maka total beban = bebanProduksiTotal saja?
        // Namun di Excel, kamu tetap tampilkan NonOp terpisah → total beban = bebanProduksiTotal + nonOperationalTotal
        $manTotalBeban = $manBebanProduksi + $manNonOperational; // ✅ sesuaikan

        $manAbsensi = $hoursToMan($absensiTotal);
        $manPowerNet = $memberHours / 8 - $manAbsensi;
        $manPenghematan = $hoursToMan($penghematanJam);

        $manPenangananItems = array_map($hoursToMan, $penangananItems);
        $manPenangananTotal = array_sum($manPenangananItems);

        // Selisih: gunakan bebanProduksiTotal yang benar
        $selisihA = $powerNetTotal - $bebanProduksiTotal;
        $manSelisihA = $manPowerNet - $manBebanProduksi; // ✅ bandingkan dengan beban produksi saja
        $selisihB = $selisihA + $penangananTotal;
        $manSelisihB = $manSelisihA + $manPenangananTotal;

        // Efisiensi: berdasarkan bebanProduksiTotal
        $efisiensiPersen = $bebanProduksiTotal > 0 ? (($bebanProduksiTotal - $powerNetTotal) / $bebanProduksiTotal) * 100 : 0;
        // NonOp persen: opsional, bisa dihitung terhadap bebanProduksiTotal
        $nonOperationalPersen = $bebanProduksiTotal > 0 ? ($nonOperationalTotal / $bebanProduksiTotal) * 100 : 0;

        // --- EXCEL ---
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Operational Performance');

        $sheet->setCellValue('A1', '2025 OPERATIONAL PERFORMANCE');
        $sheet->setCellValue('A2', '2025年の操業実績');
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('A4', 'Tanggal');
        $sheet->setCellValue('B4', $dateString);
        $sheet->mergeCells('A4:B4');
        $sheet->getStyle('A4:B4')->getFont()->setBold(true);
        $sheet->getStyle('A4:B4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension(4)->setRowHeight(20);

        $sheet->setCellValue('A7', 'Item・内容');
        $sheet->setCellValue('B7', 'Hour・時間');
        $sheet->setCellValue('C7', 'Man・人数');
        $sheet->getStyle('A7:C7')->getFont()->setBold(true);
        $sheet->getStyle('A7:C7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');

        $row = 8;

        // Beban
        $this->writeSectionHeader($sheet, $row++, 'Beban・負荷');
        // ✅ Tampilkan bebanProduksiTotal, bukan scanTotal
        $this->writeRow($sheet, $row++, 'Beban Produksi・生産負荷', $bebanProduksiTotal, $manBebanProduksi);
        $this->writeRow($sheet, $row++, 'Non operational・生産外負荷', $nonOperationalTotal, $manNonOperational);
        // Kaizen tetap = scanTotal, tapi ditampilkan negatif (pengurang)
        $this->writeRowColored($sheet, $row++, 'Kaizen・過年度工数低減 (7.8%)', -$kaizenTotal, -$manKaizen, 'FF0000FF');
        $this->writeRow($sheet, $row++, 'Part Titipan・補修部品', 0, 0);
        // ✅ Total beban = bebanProduksiTotal + nonOperationalTotal (karena Kaizen hanya label, bukan pengurang)
        $totalBebanAkhir = $bebanProduksiTotal + $nonOperationalTotal;
        $this->writeTotalRow($sheet, $row++, 'Total・計', $totalBebanAkhir, $manBebanProduksi + $manNonOperational, 'FFF0E0C0');

        // Power
        $this->writeSectionHeader($sheet, $row++, 'Power・能力');
        $this->writeRow($sheet, $row++, 'Man Power・能力', $memberHours, $memberHours / 8);
        $this->writeRowColored($sheet, $row++, 'Absensi・欠勤 (max 3%)', -$absensiTotal, -$manAbsensi, 'FF0000FF');
        $this->writeTotalRow($sheet, $row++, 'Total・計', $powerNetTotal, $manPowerNet, 'FFE0E0F0');
        $this->writeDifferenceRow($sheet, $row++, 'Selisih A (Power-Beban)', $selisihA, $manSelisihA);

        // Penanganan
        $this->writeSectionHeader($sheet, $row++, 'Penanganan・対策');
        $totalPenangananRows = count($penangananCategories);
        for ($i = 0; $i < $totalPenangananRows; $i++) {
            $label = $penangananCategories[$i][0];
            $hours = $penangananCategories[$i][1];
            $man = $manPenangananItems[$i];

            if ($i == 5) {
                $hoursDisplay = $hours < 0 ? '▲'.abs($hours) : $hours;
                $manDisplay = $man < 0 ? '▲'.abs($man) : $man;
                $this->writeRowColored($sheet, $row++, $label, $hoursDisplay, $manDisplay, 'FFFF0000');
            } else {
                $bg = $i == 0 ? 'FF00FF00' : null;
                $this->writeRowWithBackground($sheet, $row++, $label, $hours, $man, $bg);
            }
        }

        $this->writeTotalRow($sheet, $row++, 'Total・計', $penangananTotal, $manPenangananTotal, 'FFF0E0C0');
        $this->writeDifferenceRow($sheet, $row++, 'Selisih B (Selisih A + Penanganan)', $selisihB, $manSelisihB);

        // Efisiensi
        $row += 2;
        $sheet->setCellValue("A$row", "Presentase Efisiensi\n工数低減率");
        $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$row", $efisiensiPersen / 100);
        $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0.0000%');
        $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
        $row++;

        $sheet->setCellValue("A$row", "Presentase Non Operational\n非稼働工数率");
        $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$row", $nonOperationalPersen / 100);
        $sheet->getStyle("B$row")->getNumberFormat()->setFormatCode('0.0000%');
        $sheet->getStyle("B$row")->getFont()->setBold(true)->setSize(16);
        $row++;

        $sheet->getStyle('B8:C'.($row - 1))->getNumberFormat()->setFormatCode('#,##0.0000');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C'.($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C'.($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $fileName = 'Operational_Performance_'.$dateString.'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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
        $hoursDisplay = $hours < 0 ? '▲'.abs($hours) : $hours;
        $manDisplay = $man < 0 ? '▲'.abs($man) : $man;
        $color = $hours < 0 ? 'FF0000FF' : 'FF000000';

        $sheet->setCellValue("A$row", $label);
        $sheet->setCellValue("B$row", $hoursDisplay);
        $sheet->setCellValue("C$row", $manDisplay);
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB($color);
    }
}
