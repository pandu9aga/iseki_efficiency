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
            $monthParsed = Carbon::parse($request->month . '-01');
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
            $monthParsed = Carbon::parse($request->month . '-01');
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

            $scanTotal = $scans->sum('Assigned_Hour_Scan') * (1 - 0.078);
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
                'scanTotal' => $d['scanTotal'],
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
            $monthParsed = Carbon::parse($request->month . '-01');
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
        $area = $areaId ? \App\Models\Area::find($areaId) : null;
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
                    foreach ($areas as $a) {
                        $areaReport = $dayReports->get($a->Id_Area);
                        if ($areaReport) {
                            $dayMembers += (int) $areaReport->Total_Member_Report;
                            $dayHours += (float) $areaReport->Total_Hours_Report;
                        } else {
                            $ac = DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $a->Id_Area)->distinct('Nik_Daily_Job')->count();
                            $dayMembers += $ac;
                            $dayHours += ($ac * 8.0);
                        }
                    }
                }
                if ($dayMembers > 0) $daysCounted++;
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
                foreach ($areas as $a) {
                    $areaReport = $allReports->get($a->Id_Area);
                    if ($areaReport) {
                        $sumMembers += (int) $areaReport->Total_Member_Report;
                        $sumHoursManual += (float) $areaReport->Total_Hours_Report;
                    } else {
                        $areaCount = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                            ->where('Id_Area', $a->Id_Area)->distinct('Nik_Daily_Job')->count();
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
                    if ($now->gt(Carbon::today()->setTime(10, 0))) $totalHours -= 10 / 60;
                    if ($now->gt(Carbon::today()->setTime(12, 0))) $totalHours -= 40 / 60;
                    if ($now->gt(Carbon::today()->setTime(15, 0))) $totalHours -= 10 / 60;
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

        // B20: Penghematan — logika persamaan agar B29 = 0
        // B28 = SUM(B20:B27), B29 = B18 + B28
        // Agar B29 = 0 → B20 = -(B18 + SUM(B21:B27))
        // Jika hasil negatif → B20 positif, jika positif → B20 negatif
        $penghematanJam = - ($selisihA + $penangananSumWithoutPenghematan);

        // Sisipkan penghematan di posisi pertama (B20)
        array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', $penghematanJam]);

        // Rebuild items setelah prepend penghematan
        $penangananItems = array_column($penangananCategories, 1);
        // B28: Total = SUM(B20:B27)
        $penangananTotal = array_sum($penangananItems);

        // B29: Selisih B = B18 + B28 (seharusnya selalu = 0)
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

        // ====== PENANGANAN (row 19+) ======
        $this->writeSectionHeader($sheet, $row++, 'Penanganan・対策');         // row 19: header

        // Penghematan (row 20) — diisi formula di akhir
        $r_penghematan = $row;
        $sheet->setCellValue("A$row", $penangananCategories[0][0]);
        // B & C akan diisi formula setelah semua item ditulis
        $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FF00');
        $row++;

        // Item penanganan lainnya (row 21+)
        $r_firstItem = $row;
        $totalPenangananRows = count($penangananCategories);
        for ($i = 1; $i < $totalPenangananRows; $i++) {
            $label = $penangananCategories[$i][0];
            $hours = $penangananCategories[$i][1];

            if ($i == 5) {
                // Perbantuan area lain — tampilkan ▲ jika negatif
                $sheet->setCellValue("A$row", $label);
                $sheet->setCellValue("B$row", $hours);
                $sheet->setCellValue("C$row", "=B$row/8");
                $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FFFF0000');
            } else {
                $sheet->setCellValue("A$row", $label);
                $sheet->setCellValue("B$row", $hours);
                $sheet->setCellValue("C$row", "=B$row/8");
            }
            $row++;
        }
        $r_lastItem = $row - 1;

        // Formula Penghematan (B20): agar Selisih B = 0 → B20 = -(B18 + SUM(B21:B_lastItem))
        $sheet->setCellValue("B{$r_penghematan}", "=-(B{$r_selisihA}+SUM(B{$r_firstItem}:B{$r_lastItem}))");
        $sheet->setCellValue("C{$r_penghematan}", "=B{$r_penghematan}/8");

        // Total Penanganan
        $r_totalPenanganan = $row;
        $sheet->setCellValue("A$row", 'Total・計');
        $sheet->setCellValue("B$row", "=SUM(B{$r_penghematan}:B{$r_lastItem})");
        $sheet->setCellValue("C$row", "=SUM(C{$r_penghematan}:C{$r_lastItem})");
        $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
        $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
        $row++;

        // Selisih B = Selisih A + Total Penanganan (seharusnya 0)
        $r_selisihB = $row;
        $sheet->setCellValue("A$row", 'Selisih B (Selisih A + Penanganan)');
        $sheet->setCellValue("B$row", "=B{$r_selisihA}+B{$r_totalPenanganan}");
        $sheet->setCellValue("C$row", "=C{$r_selisihA}+C{$r_totalPenanganan}");
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $row++;

        // ====== EFISIENSI ======
        $row += 2;

        // Terapkan format angka bulat untuk semua data di atas (sebelum baris persentase)
        $sheet->getStyle('B8:C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

        $r_efisiensi = $row;
        $r_nonOpPersen = $row + 1;
        
        $r_bebanBottom = $row + 3;
        $r_penghematanBottom = $row + 4;
        $r_powerBottom = $row + 5;
        $r_nonOpBottom = $row + 6;

        // B33 (Dinamo): Presentase Efisiensi
        $sheet->setCellValue("A$r_efisiensi", "Presentase Efisiensi\n工数低减率");
        $sheet->getStyle("A$r_efisiensi")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_efisiensi", "=B{$r_penghematanBottom}/B{$r_bebanBottom}");
        $sheet->mergeCells("B{$r_efisiensi}:C{$r_efisiensi}");
        $sheet->getStyle("B$r_efisiensi")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_efisiensi")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_efisiensi")->getFont()->setBold(true)->setSize(16);

        // B34 (Dinamo): Presentase Non Operational
        $sheet->setCellValue("A$r_nonOpPersen", "Presentase Non Operational\n非稼働工数率");
        $sheet->getStyle("A$r_nonOpPersen")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_nonOpPersen", "=B{$r_nonOpBottom}/B{$r_powerBottom}");
        $sheet->mergeCells("B{$r_nonOpPersen}:C{$r_nonOpPersen}");
        $sheet->getStyle("B$r_nonOpPersen")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_nonOpPersen")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_nonOpPersen")->getFont()->setBold(true)->setSize(16);

        // --- BARIS PERHITUNGAN KHUSUS (DINAMIS ROW) ---
        // A37 Beban
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

        // Pastikan row tracker berada setelah baris terakhir agar layout garis border cover semuanya
        $row = $r_nonOpBottom + 1;

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '_All_Area';
        $fileName = 'Operational_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function exportMonthly(Request $request)
    {
        $monthInput = $request->get('month', Carbon::now()->format('Y-m'));
        $monthParsed = Carbon::parse($monthInput . '-01');
        $startDate = $monthParsed->copy()->startOfMonth();
        $endDate = $monthParsed->copy()->endOfMonth();
        $dateString = $monthParsed->format('Y-m');
        $monthKey = $monthParsed->format('Y-m');

        $areaId = $request->query('area');
        $area = $areaId ? \App\Models\Area::find($areaId) : null;
        $areas = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        // Ambil hari kerja dari work_days
        $workDay = \App\Models\WorkDay::where('Moth_Work_Day', $monthKey)->first();
        $totalWorkDays = $workDay ? (int) $workDay->Total_Work_Day : 0;

        if ($totalWorkDays <= 0) {
            return back()->with('error', "Hari kerja bulan {$monthKey} belum diisi. Silakan isi di menu Work Day terlebih dahulu.");
        }

        // === DATA QUERIES ===
        $scanQuery = Scan::whereDate('Time_Scan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Time_Scan', '<=', $endDate->format('Y-m-d'))->with('member', 'tractor');
        $costQuery = Cost::whereDate('Start_Cost', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Cost', '<=', $endDate->format('Y-m-d'));
        $powerQuery = Power::whereDate('Start_Power', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Power', '<=', $endDate->format('Y-m-d'))->with('member');
        $penanganansQuery = Penanganan::whereDate('Start_Penanganan', '>=', $startDate->format('Y-m-d'))
            ->whereDate('Start_Penanganan', '<=', $endDate->format('Y-m-d'));

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

        // === MEMBER HOURS (monthly sum) ===
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
                foreach ($areas as $a) {
                    $areaReport = $dayReports->get($a->Id_Area);
                    if ($areaReport) {
                        $dayMembers += (int) $areaReport->Total_Member_Report;
                        $dayHours += (float) $areaReport->Total_Hours_Report;
                    } else {
                        $ac = DailyJob::where('Production_Date_Plan', $dayYmd)->where('Id_Area', $a->Id_Area)->distinct('Nik_Daily_Job')->count();
                        $dayMembers += $ac;
                        $dayHours += ($ac * 8.0);
                    }
                }
            }
            if ($dayMembers > 0) $daysCounted++;
            $reportMembers += $dayMembers;
            $memberHours += $dayHours;
            $cursor->addDay();
        }

        // === HITUNG KOMPONEN ===
        $scanTotal = $scans->sum('Assigned_Hour_Scan');
        $nonOperationalTotal = $costs->sum('Non_Operational_Cost');
        $bebanProduksiTotal = $scanTotal;
        $absensiTotal = $powers->sum('Leave_Hour_Power');

        // === PENANGANAN ===
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

        $r_selisihA = $row;
        $sheet->setCellValue("A$row", 'Selisih A (Power-Beban)');
        $sheet->setCellValue("B$row", "=B{$r_totalPower}-B{$r_totalBeban}");
        $sheet->setCellValue("C$row", "=C{$r_totalPower}-C{$r_totalBeban}");
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $row++;

        // ====== PENANGANAN ======
        $this->writeSectionHeader($sheet, $row++, 'Penanganan・対策');

        // Penghematan (formula diisi belakangan)
        $r_penghematan = $row;
        $sheet->setCellValue("A$row", $penangananCategories[0][0]);
        $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FF00');
        $row++;

        $r_firstItem = $row;
        $totalPenangananRows = count($penangananCategories);
        for ($i = 1; $i < $totalPenangananRows; $i++) {
            $label = $penangananCategories[$i][0];
            $hours = $penangananCategories[$i][1];
            $sheet->setCellValue("A$row", $label);
            $sheet->setCellValue("B$row", $hours);
            $sheet->setCellValue("C$row", $manFormula($row));
            if ($i == 5) {
                $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FFFF0000');
            }
            $row++;
        }
        $r_lastItem = $row - 1;

        // Formula Penghematan
        $sheet->setCellValue("B{$r_penghematan}", "=-(B{$r_selisihA}+SUM(B{$r_firstItem}:B{$r_lastItem}))");
        $sheet->setCellValue("C{$r_penghematan}", $manFormula($r_penghematan));

        // Total Penanganan
        $r_totalPenanganan = $row;
        $sheet->setCellValue("A$row", 'Total・計');
        $sheet->setCellValue("B$row", "=SUM(B{$r_penghematan}:B{$r_lastItem})");
        $sheet->setCellValue("C$row", "=SUM(C{$r_penghematan}:C{$r_lastItem})");
        $sheet->getStyle("A$row:C$row")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0E0C0');
        $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
        $row++;

        // Selisih B
        $r_selisihB = $row;
        $sheet->setCellValue("A$row", 'Selisih B (Selisih A + Penanganan)');
        $sheet->setCellValue("B$row", "=B{$r_selisihA}+B{$r_totalPenanganan}");
        $sheet->setCellValue("C$row", "=C{$r_selisihA}+C{$r_totalPenanganan}");
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $row++;

        // ====== EFISIENSI ======
        $row += 2;
        $sheet->getStyle('B9:C' . ($row - 1))->getNumberFormat()->setFormatCode('0');

        $r_efisiensi = $row;
        $r_nonOpPersen = $row + 1;
        
        $r_bebanBottom = $row + 3;
        $r_penghematanBottom = $row + 4;
        $r_powerBottom = $row + 5;
        $r_nonOpBottom = $row + 6;

        // B33 (Dinamo): Presentase Efisiensi
        $sheet->setCellValue("A$r_efisiensi", "Presentase Efisiensi\n工数低减率");
        $sheet->getStyle("A$r_efisiensi")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_efisiensi", "=B{$r_penghematanBottom}/B{$r_bebanBottom}");
        $sheet->mergeCells("B{$r_efisiensi}:C{$r_efisiensi}");
        $sheet->getStyle("B$r_efisiensi")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_efisiensi")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_efisiensi")->getFont()->setBold(true)->setSize(16);

        // B34 (Dinamo): Presentase Non Operational
        $sheet->setCellValue("A$r_nonOpPersen", "Presentase Non Operational\n非稼働工数率");
        $sheet->getStyle("A$r_nonOpPersen")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_nonOpPersen", "=B{$r_nonOpBottom}/B{$r_powerBottom}");
        $sheet->mergeCells("B{$r_nonOpPersen}:C{$r_nonOpPersen}");
        $sheet->getStyle("B$r_nonOpPersen")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_nonOpPersen")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_nonOpPersen")->getFont()->setBold(true)->setSize(16);

        // --- BARIS PERHITUNGAN KHUSUS (DINAMIS ROW) ---
        // A37 Beban
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

        $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getNumberFormat()->setFormatCode('0');
        $sheet->mergeCells("B{$r_bebanBottom}:C{$r_bebanBottom}");
        $sheet->mergeCells("B{$r_penghematanBottom}:C{$r_penghematanBottom}");
        $sheet->mergeCells("B{$r_powerBottom}:C{$r_powerBottom}");
        $sheet->mergeCells("B{$r_nonOpBottom}:C{$r_nonOpBottom}");
        $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getAlignment()->setHorizontal('right');

        // Pastikan row tracker berada setelah baris terakhir agar layout garis border cover semuanya
        $row = $r_nonOpBottom + 1;

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '_All_Area';
        $fileName = 'Monthly_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';
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
