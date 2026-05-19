<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Replacement;
use App\Models\Assistance;
use App\Models\ReplacementDuration;
use App\Models\AssistanceDuration;
use App\Models\Member;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Response;

class MonitorController extends Controller
{
    /**
     * Tampilkan halaman monitoring Pergantian (Replacements)
     */
    public function replacements(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));
        $selectedMemberNik = $request->get('member_nik');

        $query = Replacement::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($selectedMemberNik) {
            $query->where('NIK_Replacement', $selectedMemberNik);
        }

        $replacements = $query->orderBy('created_at', 'desc')->get();

        // Hanya ambil member yang pernah melakukan Pergantian (Replacement)
        $replacementNiks = Replacement::distinct()->pluck('NIK_Replacement');
        $members = Member::whereIn('nik', $replacementNiks)->orderBy('nama', 'asc')->get();

        // Query durasi pergantian sesuai filter
        $durationQuery = ReplacementDuration::query();
        if ($filterType === 'daily') {
            $durationQuery->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $durationQuery->whereBetween('created_at', [$start, $end]);
        }
        if ($selectedMemberNik) {
            $durationQuery->where('NIK_Replacement', $selectedMemberNik);
        }
        $durations = $durationQuery->orderBy('created_at', 'asc')->get();

        // Group durasi per NIK + Id_Daily_Job, simpan detail per sesi
        $durationSummary = $durations->groupBy(function ($d) {
            return $d->NIK_Replacement . '|' . $d->Id_Daily_Job;
        })->map(function ($group) {
            $first = $group->first();
            $totalMinutes = $group->sum('Total_Minutes');
            $member = Member::where('nik', $first->NIK_Replacement)->first();
            $dailyJob = \App\Models\DailyJob::with('member')->find($first->Id_Daily_Job);

            // Detail per sesi
            $sessions = $group->values()->map(function ($item, $idx) {
                return [
                    'sesi' => $idx + 1,
                    'menit' => $item->Total_Minutes,
                    'waktu' => $item->created_at ? $item->created_at->format('d M Y, H:i') : '-',
                ];
            });

            return [
                'nik' => $first->NIK_Replacement,
                'nama_pengganti' => $member ? $member->nama : $first->NIK_Replacement,
                'nama_pic' => $dailyJob && $dailyJob->member ? $dailyJob->member->nama : '-',
                'total_minutes' => $totalMinutes,
                'jam' => floor($totalMinutes / 60),
                'menit' => $totalMinutes % 60,
                'jumlah_sesi' => $group->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return view('leaders.monitoring.replacements', compact('replacements', 'filterType', 'filterDate', 'filterMonth', 'durationSummary', 'members', 'selectedMemberNik'));
    }

    /**
     * Export Excel data Pergantian
     */
    public function exportReplacements(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));
        $selectedMemberNik = $request->get('member_nik');

        $query = Replacement::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($selectedMemberNik) {
            $query->where('NIK_Replacement', $selectedMemberNik);
        }

        $replacements = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Pergantian');

        // Style helper
        $sheet->setCellValue('A1', 'MONITORING PERGANTIAN (REPLACEMENT)');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        // Filter info
        $sheet->setCellValue('A3', 'Tipe Filter:');
        $sheet->setCellValue('B3', $filterType === 'daily' ? 'Harian' : 'Bulanan');
        
        $sheet->setCellValue('A4', 'Tanggal/Bulan:');
        $sheet->setCellValue('B4', $filterType === 'daily' ? Carbon::parse($filterDate)->format('d M Y') : Carbon::parse($filterMonth.'-01')->format('F Y'));

        $memberInfo = 'Semua Member';
        if ($selectedMemberNik) {
            $member = Member::where('nik', $selectedMemberNik)->first();
            $memberInfo = $member ? $member->nama . ' (' . $selectedMemberNik . ')' : $selectedMemberNik;
        }
        $sheet->setCellValue('A5', 'Filter Member:');
        $sheet->setCellValue('B5', $memberInfo);

        $sheet->getStyle('A3:A5')->getFont()->setBold(true);

        // Table headers
        $headers = [
            'No', 'Member Pengganti', 'NIK Pengganti', 'PIC Digantikan', 
            'Tractor Scanned', 'No Urut', 'Tanggal Produksi', 
            'Mower', 'Collector', 'Waktu Scan'
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '7', $h);
            $sheet->getStyle($col . '7')->getFont()->setBold(true);
            $sheet->getStyle($col . '7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
            $sheet->getStyle($col . '7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $col++;
        }

        $row = 8;
        $total = $replacements->count();
        foreach ($replacements as $idx => $item) {
            $sheet->setCellValue('A' . $row, $total - $idx);
            $sheet->setCellValue('B' . $row, $item->member ? $item->member->nama : '-');
            $sheet->setCellValue('C' . $row, $item->NIK_Replacement);
            $sheet->setCellValue('D' . $row, $item->dailyJob && $item->dailyJob->member ? $item->dailyJob->member->nama : '-');
            $sheet->setCellValue('E' . $row, $item->Name_Tractor);
            $sheet->setCellValue('F' . $row, $item->Sequence_No_Plan);
            $sheet->setCellValue('G' . $row, $item->Production_Date_Plan);
            $sheet->setCellValue('H' . $row, $item->Model_Mower_Plan ?? '-');
            $sheet->setCellValue('I' . $row, $item->Model_Collector_Plan ?? '-');
            $sheet->setCellValue('J' . $row, $item->created_at ? $item->created_at->format('d M Y, H:i') : '-');

            // Borders
            $col = 'A';
            for ($i = 0; $i < 10; $i++) {
                $sheet->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $col++;
            }
            $row++;
        }

        // Auto width
        $col = 'A';
        for ($i = 0; $i < 10; $i++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $fileName = 'Monitoring_Pergantian_' . ($filterType === 'daily' ? $filterDate : $filterMonth) . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Tampilkan halaman monitoring Perbantuan (Assistances)
     */
    public function assistances(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));
        $selectedMemberNik = $request->get('member_nik');

        $query = Assistance::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($selectedMemberNik) {
            $query->where('NIK_Assistance', $selectedMemberNik);
        }

        $assistances = $query->orderBy('created_at', 'desc')->get();

        // Hanya ambil member yang pernah melakukan Perbantuan (Assistance)
        $assistanceNiks = Assistance::distinct()->pluck('NIK_Assistance');
        $members = Member::whereIn('nik', $assistanceNiks)->orderBy('nama', 'asc')->get();

        // Query durasi perbantuan sesuai filter
        $durationQuery = AssistanceDuration::query();
        if ($filterType === 'daily') {
            $durationQuery->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $durationQuery->whereBetween('created_at', [$start, $end]);
        }
        if ($selectedMemberNik) {
            $durationQuery->where('NIK_Assistance', $selectedMemberNik);
        }
        $durations = $durationQuery->orderBy('created_at', 'asc')->get();

        // Group durasi per NIK + Id_Daily_Job, simpan detail per sesi
        $durationSummary = $durations->groupBy(function ($d) {
            return $d->NIK_Assistance . '|' . $d->Id_Daily_Job;
        })->map(function ($group) {
            $first = $group->first();
            $totalMinutes = $group->sum('Total_Minutes');
            $member = Member::where('nik', $first->NIK_Assistance)->first();
            $dailyJob = \App\Models\DailyJob::with('member')->find($first->Id_Daily_Job);

            // Detail per sesi
            $sessions = $group->values()->map(function ($item, $idx) {
                return [
                    'sesi' => $idx + 1,
                    'menit' => $item->Total_Minutes,
                    'waktu' => $item->created_at ? $item->created_at->format('d M Y, H:i') : '-',
                ];
            });

            return [
                'nik' => $first->NIK_Assistance,
                'nama_pembantu' => $member ? $member->nama : $first->NIK_Assistance,
                'nama_pic' => $dailyJob && $dailyJob->member ? $dailyJob->member->nama : '-',
                'total_minutes' => $totalMinutes,
                'jam' => floor($totalMinutes / 60),
                'menit' => $totalMinutes % 60,
                'jumlah_sesi' => $group->count(),
                'sessions' => $sessions,
            ];
        })->values();

        return view('leaders.monitoring.assistances', compact('assistances', 'filterType', 'filterDate', 'filterMonth', 'durationSummary', 'members', 'selectedMemberNik'));
    }

    /**
     * Export Excel data Perbantuan
     */
    public function exportAssistances(Request $request)
    {
        $filterType = $request->get('filter_type', 'daily');
        $filterDate = $request->get('filter_date', Carbon::today()->format('Y-m-d'));
        $filterMonth = $request->get('filter_month', Carbon::today()->format('Y-m'));
        $selectedMemberNik = $request->get('member_nik');

        $query = Assistance::with(['member', 'dailyJob.member']);

        if ($filterType === 'daily') {
            $query->whereDate('created_at', $filterDate);
        } else {
            $start = Carbon::parse($filterMonth . '-01')->startOfMonth();
            $end = Carbon::parse($filterMonth . '-01')->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($selectedMemberNik) {
            $query->where('NIK_Assistance', $selectedMemberNik);
        }

        $assistances = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monitoring Perbantuan');

        $sheet->setCellValue('A1', 'MONITORING PERBANTUAN (ASSISTANCE)');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

        $sheet->setCellValue('A3', 'Tipe Filter:');
        $sheet->setCellValue('B3', $filterType === 'daily' ? 'Harian' : 'Bulanan');
        
        $sheet->setCellValue('A4', 'Tanggal/Bulan:');
        $sheet->setCellValue('B4', $filterType === 'daily' ? Carbon::parse($filterDate)->format('d M Y') : Carbon::parse($filterMonth.'-01')->format('F Y'));

        $memberInfo = 'Semua Member';
        if ($selectedMemberNik) {
            $member = Member::where('nik', $selectedMemberNik)->first();
            $memberInfo = $member ? $member->nama . ' (' . $selectedMemberNik . ')' : $selectedMemberNik;
        }
        $sheet->setCellValue('A5', 'Filter Member:');
        $sheet->setCellValue('B5', $memberInfo);

        $sheet->getStyle('A3:A5')->getFont()->setBold(true);

        $headers = [
            'No', 'Member Perbantuan', 'NIK Perbantuan', 'PIC Dibantu', 
            'Tractor Scanned', 'No Urut', 'Tanggal Produksi', 
            'Mower', 'Collector', 'Waktu Scan'
        ];

        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '7', $h);
            $sheet->getStyle($col . '7')->getFont()->setBold(true);
            $sheet->getStyle($col . '7')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
            $sheet->getStyle($col . '7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $col++;
        }

        $row = 8;
        $total = $assistances->count();
        foreach ($assistances as $idx => $item) {
            $sheet->setCellValue('A' . $row, $total - $idx);
            $sheet->setCellValue('B' . $row, $item->member ? $item->member->nama : '-');
            $sheet->setCellValue('C' . $row, $item->NIK_Assistance);
            $sheet->setCellValue('D' . $row, $item->dailyJob && $item->dailyJob->member ? $item->dailyJob->member->nama : '-');
            $sheet->setCellValue('E' . $row, $item->Name_Tractor);
            $sheet->setCellValue('F' . $row, $item->Sequence_No_Plan);
            $sheet->setCellValue('G' . $row, $item->Production_Date_Plan);
            $sheet->setCellValue('H' . $row, $item->Model_Mower_Plan ?? '-');
            $sheet->setCellValue('I' . $row, $item->Model_Collector_Plan ?? '-');
            $sheet->setCellValue('J' . $row, $item->created_at ? $item->created_at->format('d M Y, H:i') : '-');

            $col = 'A';
            for ($i = 0; $i < 10; $i++) {
                $sheet->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $col++;
            }
            $row++;
        }

        $col = 'A';
        for ($i = 0; $i < 10; $i++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $fileName = 'Monitoring_Perbantuan_' . ($filterType === 'daily' ? $filterDate : $filterMonth) . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
