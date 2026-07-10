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
    // =========================================================================
    // FIX #1: Ekstrak logika resolusi filter ke satu helper agar tidak duplikat
    //         di index(), fullscreen(), export(), dan exportMonthly().
    //         Mengembalikan array [ startDate, endDate, dateString, isToday,
    //         filterMode, isRangeFilter, isMonthFilter ]
    // =========================================================================
    private function resolveDateFilter(Request $request): array
    {
        $isRangeFilter = $request->filled('from') && $request->filled('to');
        $isMonthFilter = !$isRangeFilter && $request->filled('month');

        if ($isRangeFilter) {
            $startDate  = Carbon::parse($request->from)->startOfDay();
            // FIX #2: endDate di-set ke endOfDay() bukan startOfDay()
            //         agar data di hari terakhir range tidak ter-miss
            //         pada query yang menggunakan datetime penuh.
            $endDate    = Carbon::parse($request->to)->endOfDay();
            $dateString = $startDate->format('Y-m-d') . ' ~ ' . Carbon::parse($request->to)->format('Y-m-d');
            $isToday    = $startDate->isToday() && Carbon::parse($request->to)->isToday();
            $filterMode = 'range';
        } elseif ($isMonthFilter) {
            $monthParsed = Carbon::parse($request->month . '-01');
            $startDate   = $monthParsed->copy()->startOfMonth();
            // FIX #3: Clamp endDate ke hari ini agar hari-hari masa depan
            //         dalam bulan berjalan tidak dihitung (data belum ada).
            $endDate     = $monthParsed->copy()->endOfMonth()->min(Carbon::today()->endOfDay());
            $dateString  = $monthParsed->format('Y-m');
            $isToday     = false;
            $filterMode  = 'month';
        } else {
            $date       = $request->filled('date')
                ? Carbon::parse($request->date)->startOfDay()
                : Carbon::today();
            $startDate  = $date->copy()->startOfDay();
            $endDate    = $date->copy()->endOfDay();
            $dateString = $date->format('Y-m-d');
            $isToday    = $date->isToday();
            $filterMode = 'date';
        }

        return compact('startDate', 'endDate', 'dateString', 'isToday', 'filterMode', 'isRangeFilter', 'isMonthFilter');
    }

    // =========================================================================
    // FIX #4: Ekstrak logika build query agar tidak duplikat di setiap method.
    // =========================================================================
    private function buildDateQueries(bool $isRangeFilter, bool $isMonthFilter, Carbon $startDate, Carbon $endDate, string $dateString): array
    {
        if ($isRangeFilter || $isMonthFilter) {
            $startStr = $startDate->format('Y-m-d');
            $endStr   = $endDate->format('Y-m-d');
            $scanQuery        = Scan::whereDate('Time_Scan', '>=', $startStr)->whereDate('Time_Scan', '<=', $endStr);
            $costQuery        = Cost::whereDate('Start_Cost', '>=', $startStr)->whereDate('Start_Cost', '<=', $endStr);
            $powerQuery       = Power::whereDate('Start_Power', '>=', $startStr)->whereDate('Start_Power', '<=', $endStr);
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', '>=', $startStr)->whereDate('Start_Penanganan', '<=', $endStr);
        } else {
            $scanQuery        = Scan::whereDate('Time_Scan', $dateString);
            $costQuery        = Cost::whereDate('Start_Cost', $dateString);
            $powerQuery       = Power::whereDate('Start_Power', $dateString);
            $penanganansQuery = Penanganan::whereDate('Start_Penanganan', $dateString);
        }

        return compact('scanQuery', 'costQuery', 'powerQuery', 'penanganansQuery');
    }

    // =========================================================================
    // FIX #5: Hitung member hours untuk mode range/monthly ke satu helper.
    //         Menghindari duplikasi di index(), fullscreen(), export(),
    //         dan exportMonthly().
    // =========================================================================
    private function calcMemberHoursRange(
        Carbon $startDate,
        Carbon $endDate,
        $areas,
        ?string $areaId,
        bool $returnAvgMembers = true
    ): array {
        $reportMembers = 0;
        $memberHours   = 0.0;
        $daysCounted   = 0;
        $todayStr      = Carbon::today()->format('Y-m-d');

        $cursor = $startDate->copy()->startOfDay();
        while ($cursor->lte($endDate)) {
            $dayStr    = $cursor->format('Y-m-d');
            $dayYmd    = $cursor->format('Ymd');
            $dayReports = Report::where('Day_Report', $dayStr)->get()->keyBy('Id_Area');

            if ($areaId) {
                $dayReport  = $dayReports->get($areaId);
                $dayMembers = $dayReport ? (int) $dayReport->Total_Member_Report : 0;
                $dayHours   = $dayReport ? (float) $dayReport->Total_Hours_Report : 0.0;
            } else {
                $dayMembers = 0;
                $dayHours   = 0.0;
                foreach ($areas as $area) {
                    $areaReport = $dayReports->get($area->Id_Area);
                    if ($areaReport) {
                        $dayMembers += (int) $areaReport->Total_Member_Report;
                        $dayHours   += (float) $areaReport->Total_Hours_Report;
                    }
                }
            }

            // Progressive untuk hari ini (gunakan method terpusat)
            if ($dayStr === $todayStr && $dayMembers > 0) {
                $dayHours = $this->calculateProgressiveMemberHours($dayMembers);
            }

            if ($dayMembers > 0) {
                $daysCounted++;
            }
            $reportMembers += $dayMembers;
            $memberHours   += $dayHours;
            $cursor->addDay();
        }

        // Untuk tampilan: rata-rata member per hari aktif
        $avgMembers = $returnAvgMembers
            ? ($daysCounted > 0 ? (int) round($reportMembers / $daysCounted) : 0)
            : $reportMembers;

        return ['reportMembers' => $avgMembers, 'memberHours' => $memberHours];
    }

    // =========================================================================
    // FIX #6: Hitung member hours untuk mode daily ke satu helper.
    // =========================================================================
    private function calcMemberHoursDaily(
        Carbon $startDate,
        string $dateString,
        bool $isToday,
        $areas,
        ?string $areaId
    ): array {
        $productionDateYmd = $startDate->format('Ymd');
        $allReports        = Report::where('Day_Report', $dateString)->get()->keyBy('Id_Area');

        // FIX #7: Inisialisasi eksplisit $sumHoursManual = 0.0 sebelum cabang
        //         agar tidak ada risiko "undefined variable" jika cabang ditambah/refactor.
        $sumHoursManual = 0.0;
        $reportMembers  = 0;

        if ($areaId) {
            $report = $allReports->get($areaId);
            if ($report) {
                $reportMembers  = (int) $report->Total_Member_Report;
                $sumHoursManual = (float) $report->Total_Hours_Report;
            } else {
                $reportMembers  = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                    ->where('Id_Area', $areaId)->distinct('Nik_Daily_Job')->count();
                $sumHoursManual = $reportMembers * 8.0;
            }
        } else {
            $sumMembers = 0;
            foreach ($areas as $area) {
                $areaReport = $allReports->get($area->Id_Area);
                if ($areaReport) {
                    $sumMembers     += (int) $areaReport->Total_Member_Report;
                    $sumHoursManual += (float) $areaReport->Total_Hours_Report;
                } else {
                    $areaCount       = DailyJob::where('Production_Date_Plan', $productionDateYmd)
                        ->where('Id_Area', $area->Id_Area)->distinct('Nik_Daily_Job')->count();
                    $sumMembers     += $areaCount;
                    $sumHoursManual += $areaCount * 8.0;
                }
            }
            $reportMembers = $sumMembers;
        }

        if ($isToday) {
            $memberHours = $this->calculateProgressiveMemberHours($reportMembers);
        } else {
            $memberHours = $sumHoursManual;
        }

        return compact('reportMembers', 'memberHours');
    }

    // =========================================================================
    // FIX #8: Ekstrak logika kategorisasi penanganan agar tidak duplikat
    //         di export() dan exportMonthly().
    // =========================================================================
    private function buildPenangananCategories($penanganans): array
    {
        $fixedLabels = [
            'fix_back_up'      => 'Fix Back Up Proses / 工程の応援',
            'back_up_absensi'  => 'Back Up Absensi / 欠勤応援',
            'bantuan_pic'      => 'Bantuan ke PIC Absensi / 欠勤対応の応援',
            'irregular'        => 'Back Up Line Stop / Irregular / イレギュラー対応',
            'area_lain'        => 'Perbantuan area lain / 他部署応援 【－】',
            'lembur_produksi'  => 'Lembur Produksi / 生産残業',
            'lembur_mente'     => 'Lembur Mente / メンテ残業',
        ];

        $handlingValues = array_fill_keys(array_keys($fixedLabels), 0.0);
        $manualEntries  = [];

        foreach ($penanganans as $p) {
            $desc      = $p->Keterangan_Penanganan;
            $hours     = (float) $p->Hour_Penanganan;
            $descLower = strtolower($desc);
            $matched   = false;

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

        $categories = [
            [$fixedLabels['fix_back_up'],     $handlingValues['fix_back_up']],
            [$fixedLabels['back_up_absensi'], $handlingValues['back_up_absensi']],
            [$fixedLabels['bantuan_pic'],      $handlingValues['bantuan_pic']],
            [$fixedLabels['irregular'],        $handlingValues['irregular']],
            [$fixedLabels['area_lain'],        $handlingValues['area_lain']],
            [$fixedLabels['lembur_produksi'],  $handlingValues['lembur_produksi']],
            [$fixedLabels['lembur_mente'],     $handlingValues['lembur_mente']],
        ];

        foreach ($manualEntries as $entry) {
            $categories[] = [$entry['label'], $entry['hours']];
        }

        return $categories;
    }

    // =========================================================================
    // FIX #9: Ekstrak penulisan blok Excel Beban + Power + Penanganan agar
    //         export() dan exportMonthly() tidak duplikasi ~200 baris.
    //         Mengembalikan array row-reference yang dibutuhkan untuk
    //         kalkulasi efisiensi di bawahnya.
    // =========================================================================
    private function writeExcelBody(
        $sheet,
        int &$row,
        float $bebanProduksiTotal,
        float $nonOperationalTotal,
        float $memberHours,
        float $absensiTotal,
        array $penangananCategories,
        callable $manFormula      // fn(int $r): string
    ): array {
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
        $sheet->getStyle("A$row:C$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF0E0C0');
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
        $sheet->getStyle("A$row:C$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE0E0F0');
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

        // Penghematan — formula diisi setelah semua item ditulis
        $r_penghematan = $row;
        $sheet->setCellValue("A$row", $penangananCategories[0][0]);
        $sheet->getStyle("A$row:C$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF00FF00');
        $row++;

        $r_firstItem        = $row;
        $totalPenangananRows = count($penangananCategories);
        for ($i = 1; $i < $totalPenangananRows; $i++) {
            [$label, $hours] = $penangananCategories[$i];
            $sheet->setCellValue("A$row", $label);
            $sheet->setCellValue("B$row", $hours);
            $sheet->setCellValue("C$row", $manFormula($row));
            // index 5 = Perbantuan area lain → warna merah
            if ($i === 5) {
                $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB('FFFF0000');
            }
            $row++;
        }
        $r_lastItem = $row - 1;

        // FIX #10: Guard agar formula power-bottom tidak salah jika item < 6
        //          dengan memastikan p1_end tidak melampaui r_lastItem.
        $p1_start = $r_firstItem;
        $p1_end   = min($r_firstItem + 3, $r_lastItem);
        // FIX #11: Hanya tambahkan range p2 jika memang ada item setelah index 5
        $p2_start = $r_firstItem + 5;
        $p2_end   = $r_lastItem;
        $hasp2    = $p2_start <= $p2_end;

        // Formula Penghematan: B_penghematan = -(SelisihA + SUM(item lain))
        $sheet->setCellValue("B{$r_penghematan}", "=-(B{$r_selisihA}+SUM(B{$r_firstItem}:B{$r_lastItem}))");
        $sheet->setCellValue("C{$r_penghematan}", $manFormula($r_penghematan));

        // Total Penanganan
        $r_totalPenanganan = $row;
        $sheet->setCellValue("A$row", 'Total・計');
        $sheet->setCellValue("B$row", "=SUM(B{$r_penghematan}:B{$r_lastItem})");
        $sheet->setCellValue("C$row", "=SUM(C{$r_penghematan}:C{$r_lastItem})");
        $sheet->getStyle("A$row:C$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF0E0C0');
        $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
        $row++;

        // Selisih B
        $r_selisihB = $row;
        $sheet->setCellValue("A$row", 'Selisih B (Selisih A + Penanganan)');
        $sheet->setCellValue("B$row", "=B{$r_selisihA}+B{$r_totalPenanganan}");
        $sheet->setCellValue("C$row", "=C{$r_selisihA}+C{$r_totalPenanganan}");
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $row++;

        return compact(
            'r_bebanProduksi',
            'r_nonOp',
            'r_kaizen',
            'r_partTitipan',
            'r_totalBeban',
            'r_manPower',
            'r_absensi',
            'r_totalPower',
            'r_selisihA',
            'r_penghematan',
            'r_firstItem',
            'r_lastItem',
            'r_totalPenanganan',
            'r_selisihB',
            'p1_start',
            'p1_end',
            'p2_start',
            'p2_end',
            'hasp2'
        );
    }

    // =========================================================================
    // FIX #12: Ekstrak penulisan baris efisiensi (bagian bawah Excel)
    //          agar tidak duplikat di export() dan exportMonthly().
    // =========================================================================
    private function writeExcelEfficiency($sheet, int &$row, array $refs, string $numberFormat): void
    {
        extract($refs); // r_bebanProduksi, r_nonOp, r_kaizen, r_partTitipan,
        // r_manPower, r_penghematan, r_firstItem, r_lastItem,
        // p1_start, p1_end, p2_start, p2_end, hasp2

        $row += 2;

        $r_efisiensi    = $row;
        $r_nonOpPersen  = $row + 1;
        $r_bebanBottom  = $row + 3;
        $r_pengBottom   = $row + 4;
        $r_powerBottom  = $row + 5;
        $r_nonOpBottom  = $row + 6;

        // Presentase Efisiensi
        $sheet->setCellValue("A$r_efisiensi", "Presentase Efisiensi\n工数低减率");
        $sheet->getStyle("A$r_efisiensi")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_efisiensi", "=B{$r_pengBottom}/B{$r_bebanBottom}");
        $sheet->mergeCells("B{$r_efisiensi}:C{$r_efisiensi}");
        $sheet->getStyle("B$r_efisiensi")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_efisiensi")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_efisiensi")->getFont()->setBold(true)->setSize(16);

        // Presentase Non Operational
        $sheet->setCellValue("A$r_nonOpPersen", "Presentase Non Operational\n非稼働工数率");
        $sheet->getStyle("A$r_nonOpPersen")->getAlignment()->setWrapText(true);
        $sheet->setCellValue("B$r_nonOpPersen", "=B{$r_nonOpBottom}/B{$r_powerBottom}");
        $sheet->mergeCells("B{$r_nonOpPersen}:C{$r_nonOpPersen}");
        $sheet->getStyle("B$r_nonOpPersen")->getAlignment()->setHorizontal('right');
        $sheet->getStyle("B$r_nonOpPersen")->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("B$r_nonOpPersen")->getFont()->setBold(true)->setSize(16);

        // Beban
        $sheet->setCellValue("A{$r_bebanBottom}", "Beban");
        $sheet->setCellValue("B{$r_bebanBottom}", "=C{$r_bebanProduksi}+C{$r_kaizen}+C{$r_partTitipan}");

        // Penghematan
        $sheet->setCellValue("A{$r_pengBottom}", "Penghematan");
        $sheet->setCellValue("B{$r_pengBottom}", "=C{$r_penghematan}");

        // Power: sum item 1-4 + item 6+ + manPower
        // FIX #13: Bangun formula power secara kondisional agar tidak error
        //          jika item penanganan < 6 (p2_start > p2_end)
        $powerFormula = $hasp2
            ? "=SUM(C{$p1_start}:C{$p1_end},C{$p2_start}:C{$p2_end})+C{$r_manPower}"
            : "=SUM(C{$p1_start}:C{$p1_end})+C{$r_manPower}";
        $sheet->setCellValue("A{$r_powerBottom}", "Power");
        $sheet->setCellValue("B{$r_powerBottom}", $powerFormula);

        // Non Operational
        $sheet->setCellValue("A{$r_nonOpBottom}", "Non Operational");
        $sheet->setCellValue("B{$r_nonOpBottom}", "=C{$r_nonOp}");

        $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getNumberFormat()->setFormatCode($numberFormat);
        $sheet->mergeCells("B{$r_bebanBottom}:C{$r_bebanBottom}");
        $sheet->mergeCells("B{$r_pengBottom}:C{$r_pengBottom}");
        $sheet->mergeCells("B{$r_powerBottom}:C{$r_powerBottom}");
        $sheet->mergeCells("B{$r_nonOpBottom}:C{$r_nonOpBottom}");
        $sheet->getStyle("B{$r_bebanBottom}:B{$r_nonOpBottom}")->getAlignment()->setHorizontal('right');

        $row = $r_nonOpBottom + 1;
    }

    // =========================================================================
    // index()
    // =========================================================================
    public function index(Request $request)
    {
        $filter = $this->resolveDateFilter($request);
        extract($filter); // startDate, endDate, dateString, isToday, filterMode, isRangeFilter, isMonthFilter

        $areas  = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
        $areaId = $request->query('area');

        ['scanQuery' => $scanQuery, 'costQuery' => $costQuery, 'powerQuery' => $powerQuery, 'penanganansQuery' => $penanganansQuery]
            = $this->buildDateQueries($isRangeFilter, $isMonthFilter, $startDate, $endDate, $dateString);

        $scanQuery->with('tractor');
        $powerQuery->with('member');

        if ($areaId) {
            $scanQuery->where('Id_Area', $areaId);
            $costQuery->where('Id_Area', $areaId);
            $powerQuery->where('Id_Area', $areaId);
            $penanganansQuery->where('Id_Area', $areaId);
        }

        $scans       = $scanQuery->get();
        $costs       = $costQuery->get();
        $powers      = $powerQuery->get();
        $penanganans = $penanganansQuery->get();

        $costImpactList = $costs->map(fn($c) => [
            'label' => $c->Keterangan_Cost ?? 'Unknown',
            'value' => (float) $c->Non_Operational_Cost,
        ])->toArray();

        $powerTotal = $powers->sum('Leave_Hour_Power');

        if ($isRangeFilter || $isMonthFilter) {
            ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                = $this->calcMemberHoursRange($startDate, $endDate, $areas, $areaId);
        } else {
            ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                = $this->calcMemberHoursDaily($startDate, $dateString, $isToday, $areas, $areaId);
        }

        $memberHoursText = $this->formatHoursToText($memberHours);

        $scansForJs = $scans->map(fn($s) => [
            'label' => $s->tractor?->Name_Tractor ?? 'Unknown',
            'value' => (float) $s->Assigned_Hour_Scan * (1 - 0.078),
        ])->toArray();

        $powersForJs = $powers->map(fn($p) => [
            'label' => $p->Keterangan_Power ?? 'Unknown',
            'value' => (float) $p->Leave_Hour_Power,
        ])->toArray();

        $penanganansForJs = $penanganans->map(fn($p) => [
            'label' => $p->Keterangan_Penanganan ?? 'Unknown',
            'value' => (float) $p->Hour_Penanganan,
        ])->toArray();

        $dashboardJsData = [
            'rawScans'       => $scansForJs,
            'rawCosts'       => $costImpactList,
            'rawPowers'      => $powersForJs,
            'rawPenanganans' => $penanganansForJs,
            'memberHours'    => (float) $memberHours,
            'reportMembers'  => (int) $reportMembers,
            'powerTotal'     => (float) $powerTotal,
        ];

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

    // =========================================================================
    // fullscreen()
    // =========================================================================
    public function fullscreen(Request $request)
    {
        $filter = $this->resolveDateFilter($request);
        extract($filter);

        $areas    = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
        $areaData = [];

        foreach ($areas as $area) {
            $areaId = $area->Id_Area;

            ['scanQuery' => $sq, 'costQuery' => $cq, 'powerQuery' => $pq, 'penanganansQuery' => $penq]
                = $this->buildDateQueries($isRangeFilter, $isMonthFilter, $startDate, $endDate, $dateString);

            $scans       = $sq->with('tractor')->where('Id_Area', $areaId)->get();
            $costs       = $cq->where('Id_Area', $areaId)->get();
            $powers      = $pq->with('member')->where('Id_Area', $areaId)->get();
            $penanganans = $penq->where('Id_Area', $areaId)->get();

            if ($isRangeFilter || $isMonthFilter) {
                ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                    = $this->calcMemberHoursRange($startDate, $endDate, $areas, (string) $areaId);
            } else {
                ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                    = $this->calcMemberHoursDaily($startDate, $dateString, $isToday, $areas, (string) $areaId);
            }

            $scanTotal       = $scans->sum('Assigned_Hour_Scan') * (1 - 0.078);
            $costTotal       = $costs->sum('Non_Operational_Cost');
            $penangananTotal = $penanganans->sum('Hour_Penanganan');
            $areaLainTotal   = $penanganans->filter(function ($p) {
                $desc = strtolower($p->Keterangan_Penanganan);
                return str_contains($desc, 'area lain') || str_contains($desc, '他部署応援');
            })->sum('Hour_Penanganan');
            $powerTotal      = $powers->sum('Leave_Hour_Power');
            $reportNetHours  = $memberHours - $powerTotal;
            $kategori1       = $reportNetHours + $penangananTotal;
            $kategori2       = $scanTotal + $costTotal;
            $selisihJam      = $kategori2 - $kategori1;
            $nilaiRupiah     = $selisihJam * 60000;

            $areaData[] = [
                'area'            => $area,
                'reportMembers'   => $reportMembers,
                'memberHours'     => $memberHours,
                'reportNetHours'  => $reportNetHours,
                'scanTotal'       => $scanTotal,
                'costTotal'       => $costTotal,
                'penangananTotal' => $penangananTotal,
                'areaLainTotal'   => $areaLainTotal,
                'powerTotal'      => $powerTotal,
                'selisihJam'      => $selisihJam,
                'nilaiRupiah'     => $nilaiRupiah,
            ];
        }

        $chartDataJson = array_map(fn($d) => [
            'id'              => $d['area']->Id_Area,
            'reportNetHours'  => max(0, $d['reportNetHours']),
            'penangananTotal' => $d['penangananTotal'],
            'scanTotal'       => $d['scanTotal'],
            'costTotal'       => $d['costTotal'],
        ], $areaData);

        return view('admins.dashboard-fullscreen', compact('dateString', 'isToday', 'areaData', 'chartDataJson', 'filterMode'));
    }

    // =========================================================================
    // calculateProgressiveMemberHours() — satu-satunya tempat logika ini ada
    // =========================================================================
    private function calculateProgressiveMemberHours(int $memberCount): float
    {
        $now       = Carbon::now();
        $start     = Carbon::today()->setTime(7, 30);
        $endOfWork = Carbon::today()->setTime(16, 30);

        if ($now->lt($start)) {
            return 0.0;
        }

        if ($now->gt($endOfWork)) {
            return $memberCount * 8.0;
        }

        $totalHours = $start->diffInRealSeconds($now) / 3600.0;
        if ($now->gt(Carbon::today()->setTime(10, 0)))  $totalHours -= 10 / 60;
        if ($now->gt(Carbon::today()->setTime(12, 0)))  $totalHours -= 40 / 60;
        if ($now->gt(Carbon::today()->setTime(15, 0)))  $totalHours -= 10 / 60;
        $totalHours = max(0.0, $totalHours);

        return $memberCount * min($totalHours, 8.0);
    }

    private function formatHoursToText(float $totalHours): string
    {
        if ($totalHours <= 0) {
            return '0 jam 0 menit';
        }
        $hours   = (int) floor($totalHours);
        $minutes = (int) round(($totalHours - $hours) * 60);
        if ($minutes >= 60) {
            $hours  += (int) floor($minutes / 60);
            $minutes = $minutes % 60;
        }
        return "{$hours} jam {$minutes} menit";
    }

    // =========================================================================
    // export()  — export harian / range
    // =========================================================================
    public function export(Request $request)
    {
        $filter = $this->resolveDateFilter($request);
        extract($filter);

        $areaId = $request->query('area');
        $area   = $areaId ? \App\Models\Area::find($areaId) : null;
        $areas  = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        ['scanQuery' => $scanQuery, 'costQuery' => $costQuery, 'powerQuery' => $powerQuery, 'penanganansQuery' => $penanganansQuery]
            = $this->buildDateQueries($isRangeFilter, $isMonthFilter, $startDate, $endDate, $dateString);

        $scanQuery->with('member', 'tractor');
        $powerQuery->with('member');

        if ($areaId) {
            $scanQuery->where('Id_Area', $areaId);
            $costQuery->where('Id_Area', $areaId);
            $powerQuery->where('Id_Area', $areaId);
            $penanganansQuery->where('Id_Area', $areaId);
        }

        $scans       = $scanQuery->get();
        $costs       = $costQuery->get();
        $powers      = $powerQuery->get();
        $penanganans = $penanganansQuery->get();

        if ($isRangeFilter || $isMonthFilter) {
            ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                = $this->calcMemberHoursRange($startDate, $endDate, $areas, $areaId);
        } else {
            ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
                = $this->calcMemberHoursDaily($startDate, $dateString, $isToday, $areas, $areaId);
        }

        $scanTotal           = $scans->sum('Assigned_Hour_Scan');
        $nonOperationalTotal = $costs->sum('Non_Operational_Cost');
        $bebanProduksiTotal  = $scanTotal;
        $absensiTotal        = $powers->sum('Leave_Hour_Power');

        $penangananCategories = $this->buildPenangananCategories($penanganans);
        // Penghematan disisipkan di posisi 0 — value-nya diisi via formula di Excel
        array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', 0]);

        // --- EXCEL ---
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Operational Performance');

        // FIX #14: Gunakan $startDate->format('Y') langsung — tidak perlu
        //          Carbon::parse($dateString) yang akan crash saat dateString
        //          berformat "2024-01-01 ~ 2024-01-31".
        $exportYear = $startDate->format('Y');
        $sheet->setCellValue('A1', $exportYear . ' OPERATIONAL PERFORMANCE');
        $sheet->setCellValue('A2', $exportYear . '年の操業実績');
        $sheet->mergeCells('A1:C1');
        $sheet->mergeCells('A2:C2');
        $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal('center');

        if ($isRangeFilter) {
            $sheet->setCellValue('A4', 'Periode / 期間');
            $sheet->setCellValue('B4', $startDate->format('d F Y') . ' - ' . Carbon::parse($request->to)->format('d F Y'));
        } else {
            $sheet->setCellValue('A4', 'Tanggal / 日付');
            $sheet->setCellValue('B4', $startDate->format('d F Y'));
        }
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
        $sheet->getStyle('A7:C7')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEEEEEE');

        $row      = 8;

        if ($isRangeFilter) {
            $rangeDays = \App\Models\SpecialDate::countWorkdays(
                $startDate->format('Y-m-d'),
                Carbon::parse($request->to)->format('Y-m-d')
            );
            $sheet->setCellValue('A6', 'Hari / 日数');
            $sheet->setCellValue('B6', $rangeDays);
            $sheet->mergeCells('B6:C6');
            $sheet->getStyle('B6')->getAlignment()->setHorizontal('left');
            $sheet->getStyle('A6:C6')->getFont()->setBold(true);
            $sheet->getStyle('A6:C6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getRowDimension(6)->setRowHeight(20);
            // Untuk export range: Man = Jam ÷ 8 ÷ jumlah hari range
            $manFn    = fn(int $r): string => "=B{$r}/8/\$B\$6";
        } else {
            // Untuk export harian: Man = Jam ÷ 8
            $manFn    = fn(int $r): string => "=B{$r}/8";
        }

        $refs = $this->writeExcelBody(
            $sheet,
            $row,
            $bebanProduksiTotal,
            $nonOperationalTotal,
            $memberHours,
            $absensiTotal,
            $penangananCategories,
            $manFn
        );

        // Format angka bulat untuk semua baris data
        $sheet->getStyle('B8:C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');

        $this->writeExcelEfficiency($sheet, $row, $refs, '#,##0');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '_All_Area';
        $fileName   = 'Operational_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';

        return $this->streamExcel($spreadsheet, $fileName);
    }

    // =========================================================================
    // exportMonthly()  — export bulanan (Man = Jam ÷ 8 ÷ HariKerja)
    // =========================================================================
    public function exportMonthly(Request $request)
    {
        $monthInput  = $request->get('month', Carbon::now()->format('Y-m'));
        $monthParsed = Carbon::parse($monthInput . '-01');
        $startDate   = $monthParsed->copy()->startOfMonth();
        // FIX #15: Clamp endDate ke hari ini agar bulan berjalan tidak hitung hari depan
        $endDate     = $monthParsed->copy()->endOfMonth()->min(Carbon::today()->endOfDay());
        $dateString  = $monthParsed->format('Y-m');
        $monthKey    = $monthParsed->format('Y-m');

        $areaId = $request->query('area');
        $area   = $areaId ? \App\Models\Area::find($areaId) : null;
        $areas  = \App\Models\Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();

        $totalWorkDays = \App\Models\SpecialDate::countWorkdays(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        if ($totalWorkDays <= 0) {
            return back()->with('error', "Tidak ada hari kerja di bulan {$monthKey}.");
        }

        $startStr = $startDate->format('Y-m-d');
        $endStr   = $endDate->format('Y-m-d');

        $scanQuery        = Scan::whereDate('Time_Scan', '>=', $startStr)->whereDate('Time_Scan', '<=', $endStr)->with('member', 'tractor');
        $costQuery        = Cost::whereDate('Start_Cost', '>=', $startStr)->whereDate('Start_Cost', '<=', $endStr);
        $powerQuery       = Power::whereDate('Start_Power', '>=', $startStr)->whereDate('Start_Power', '<=', $endStr)->with('member');
        $penanganansQuery = Penanganan::whereDate('Start_Penanganan', '>=', $startStr)->whereDate('Start_Penanganan', '<=', $endStr);

        if ($areaId) {
            $scanQuery->where('Id_Area', $areaId);
            $costQuery->where('Id_Area', $areaId);
            $powerQuery->where('Id_Area', $areaId);
            $penanganansQuery->where('Id_Area', $areaId);
        }

        $scans       = $scanQuery->get();
        $costs       = $costQuery->get();
        $powers      = $powerQuery->get();
        $penanganans = $penanganansQuery->get();

        ['reportMembers' => $reportMembers, 'memberHours' => $memberHours]
            = $this->calcMemberHoursRange($startDate, $endDate, $areas, $areaId);

        $scanTotal           = $scans->sum('Assigned_Hour_Scan');
        $nonOperationalTotal = $costs->sum('Non_Operational_Cost');
        $bebanProduksiTotal  = $scanTotal;
        $absensiTotal        = $powers->sum('Leave_Hour_Power');

        $penangananCategories = $this->buildPenangananCategories($penanganans);
        array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', 0]);

        // --- EXCEL ---
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Performance');

        $exportYear      = $monthParsed->format('Y');
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

        $sheet->setCellValue('A7', 'Item・内容');
        $sheet->setCellValue('B7', 'Hour・時間');
        $sheet->setCellValue('C7', 'Man・人数');
        $sheet->getStyle('A7:C7')->getFont()->setBold(true);
        $sheet->getStyle('A7:C7')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEEEEEE');

        $row   = 8;
        // Untuk export bulanan: Man = Jam ÷ 8 ÷ HariKerja (referensi B5)
        $manFn = fn(int $r): string => "=B{$r}/8/\$B\$5";

        $refs = $this->writeExcelBody(
            $sheet,
            $row,
            $bebanProduksiTotal,
            $nonOperationalTotal,
            $memberHours,
            $absensiTotal,
            $penangananCategories,
            $manFn
        );

        // Format angka untuk data (mulai B9 karena B8 adalah header section)
        $sheet->getStyle('B9:C' . ($row - 1))->getNumberFormat()->setFormatCode('0');

        $this->writeExcelEfficiency($sheet, $row, $refs, '0');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $areaSuffix = $area ? '_' . str_replace(' ', '_', $area->Name_Area) : '_All_Area';
        $fileName   = 'Monthly_Performance' . $areaSuffix . '_' . $dateString . '.xlsx';

        return $this->streamExcel($spreadsheet, $fileName);
    }

    // =========================================================================
    // FIX #16: Ekstrak stream Excel agar tidak duplikat di export() dan
    //          exportMonthly() (tempnam + save + download + deleteAfterSend).
    // =========================================================================
    private function streamExcel(Spreadsheet $spreadsheet, string $fileName)
    {
        $writer   = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // =========================================================================
    // Helper Excel (tetap ada agar backward-compatible jika ada caller lain)
    // =========================================================================
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
            $sheet->getStyle("A$row:C$row")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($bgColor);
        }
    }

    private function writeTotalRow($sheet, int $row, string $label, $hours, $man, string $bgColor): void
    {
        $this->writeRow($sheet, $row, $label, $hours, $man);
        $sheet->getStyle("A$row:C$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB($bgColor);
        $sheet->getStyle("A$row:C$row")->getFont()->setBold(true);
    }

    private function writeDifferenceRow($sheet, int $row, string $label, float $hours, float $man): void
    {
        $hoursDisplay = $hours < 0 ? '▲' . abs($hours) : $hours;
        $manDisplay   = $man < 0   ? '▲' . abs($man)   : $man;
        $color        = $hours < 0 ? 'FF0000FF' : 'FF000000';

        $sheet->setCellValue("A$row", $label);
        $sheet->setCellValue("B$row", $hoursDisplay);
        $sheet->setCellValue("C$row", $manDisplay);
        $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
        $sheet->getStyle("B$row:C$row")->getFont()->getColor()->setARGB($color);
    }
}
