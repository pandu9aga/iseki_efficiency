<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Report;
use App\Models\ListMember; // ✅ tambahkan ini
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Scan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AdminController extends Controller
{
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

    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $dateString = $date->format('Y-m-d');
        $isToday = $date->isToday();

        // ✅ Ambil jumlah member aktif dari list_member
        $currentTotalMembers = ListMember::count();

        $scans = Scan::whereDate('Time_Scan', $dateString)->with('tractor')->get();
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();

        // ✅ Hitung Non-Operational Impact (sudah dikalikan member)
        $costImpactTotal = $costs->sum('Non_Operational_Cost') * $currentTotalMembers;
        $costImpactList = $costs->map(function ($cost) use ($currentTotalMembers) {
            return [
                'label' => $cost->Keterangan_Cost ?? 'Unknown',
                'value' => (float) $cost->Non_Operational_Cost * $currentTotalMembers,
            ];
        })->toArray();

        $report = Report::where('Day_Report', $dateString)->first();
        $reportMembers = $report ? (int) $report->Total_Member_Report : $currentTotalMembers;

        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();
        $powerTotal = $powers->sum('Leave_Hour_Power');

        if ($isToday) {
            $now = Carbon::now();
            $start = Carbon::today()->setTime(7, 30);
            $endOfWork = Carbon::today()->setTime(16, 30);

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
            $memberHours = $report ? (float) $report->Total_Hours_Report : ($reportMembers * 8.0);
        }

        $memberHoursText = $this->formatHoursToText($memberHours);

        return view('admins.dashboard', compact(
            'scans',
            'costs',
            'memberHours',
            'memberHoursText',
            'reportMembers',
            'powers',
            'penanganans',
            'powerTotal',
            'dateString',
            'isToday',
            'currentTotalMembers', // ✅ kirim ke view
            'costImpactList',      // ✅ untuk chart
            'costImpactTotal'      // ✅ untuk total (opsional)
        ));
    }

    public function export(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        $dateString = $date->format('Y-m-d');
        $isToday = $date->isToday();

        // Ambil data
        $scans = Scan::whereDate('Time_Scan', $dateString)->with('member', 'tractor')->get();
        $costs = Cost::whereDate('Start_Cost', $dateString)->get();
        $report = Report::where('Day_Report', $dateString)->first();
        $powers = Power::whereDate('Start_Power', $dateString)->with('member')->get();
        $penanganans = Penanganan::whereDate('Start_Penanganan', $dateString)->get();

        $reportMembers = is_numeric($report?->Total_Member_Report) ? (int) $report->Total_Member_Report : 0;

        if ($isToday) {
            $now = Carbon::now();
            $start = Carbon::today()->setTime(7, 30);
            $endOfWork = Carbon::today()->setTime(16, 30);

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
            $memberHours = $report ? (float) $report->Total_Hours_Report : 0.0;
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

        $penangananItems = array_column($penangananCategories, 1);
        $penangananTotal = array_sum($penangananItems);

        // Penghematan: sesuaikan dengan logika baru (NonOp tetap dihitung di sini untuk penghematan)
        $penghematanJam = ($scanTotal + $nonOperationalTotal) - ($powerNetTotal + $penangananTotal);

        array_unshift($penangananCategories, ['Penghematan Jam Bulan ini / 今月の工数低減', $penghematanJam]);
        array_unshift($penangananItems, $penghematanJam);

        // --- Konversi ke Man ---
        $hoursToMan = fn(float $h): float => $h / 8;

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
        $spreadsheet = new Spreadsheet();
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
                $hoursDisplay = $hours < 0 ? "▲" . abs($hours) : $hours;
                $manDisplay = $man < 0 ? "▲" . abs($man) : $man;
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

        $sheet->getStyle('B8:C' . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.0000');

        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getStyle('A1:C' . ($row - 1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A1:C' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $fileName = 'Operational_Performance_' . $dateString . '.xlsx';
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
