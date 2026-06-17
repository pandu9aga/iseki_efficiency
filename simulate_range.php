<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Scan;
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Report;
use App\Models\Area;
use Carbon\Carbon;

$from = '2026-04-01';
$to   = '2026-04-30';
$areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
$todayStr = Carbon::today()->format('Y-m-d');
$startDate = Carbon::parse($from)->startOfDay();
$endDate = Carbon::parse($to)->endOfDay();

echo "=== FULL SIMULATION (seperti controller) ===\n";
echo "Range: $from ~ $to\n";
echo "Today: $todayStr\n\n";

// Simulasi per-area dan all-areas
echo "--- PER AREA ---\n";
$perAreaResults = [];
foreach ($areas as $a) {
    // Scans per area
    $scans = Scan::where('Id_Area', $a->Id_Area)
        ->whereDate('Time_Scan', '>=', $from)->whereDate('Time_Scan', '<=', $to)
        ->get();
    $scanTotal = $scans->sum('Assigned_Hour_Scan') * (1 - 0.078);
    
    // Costs per area
    $costTotal = Cost::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Cost', '>=', $from)->whereDate('Start_Cost', '<=', $to)
        ->sum('Non_Operational_Cost');
    
    // Powers per area
    $powers = Power::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Power', '>=', $from)->whereDate('Start_Power', '<=', $to)
        ->get();
    $powerTotal = $powers->sum('Leave_Hour_Power');
    
    // Penanganan per area
    $penanganans = Penanganan::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Penanganan', '>=', $from)->whereDate('Start_Penanganan', '<=', $to)
        ->get();
    $penangananTotal = $penanganans->sum('Hour_Penanganan');
    
    // Member hours per area
    $memberHours = 0;
    $cursor = $startDate->copy();
    while ($cursor->lte($endDate)) {
        $dayStr = $cursor->format('Y-m-d');
        $report = Report::where('Day_Report', $dayStr)->where('Id_Area', $a->Id_Area)->first();
        if ($report) {
            $dayHours = (float) $report->Total_Hours_Report;
            if ($dayStr === $todayStr) {
                // progressive (pakai 8 jam untuk test pada range lampau karena hari ini adalah 2026-06-08, di luar range)
                $dayHours = (int) $report->Total_Member_Report * 8.0;
            }
            $memberHours += $dayHours;
        }
        $cursor->addDay();
    }
    
    $reportNetHours = $memberHours - $powerTotal;
    $kategori1 = $reportNetHours + $penangananTotal;
    $kategori2 = $scanTotal + $costTotal;
    $selisihJam = $kategori2 - $kategori1;
    $persenOp = $scanTotal != 0 ? ($selisihJam / $scanTotal) * 100 : 0;
    
    $perAreaResults[$a->Name_Area] = compact('scanTotal', 'costTotal', 'powerTotal', 'penangananTotal', 'memberHours', 'reportNetHours', 'selisihJam', 'persenOp');
    
    printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f persen=%-6.2f%%\n",
        $a->Name_Area, $scanTotal, $costTotal, $powerTotal, $penangananTotal, $memberHours, $reportNetHours, $selisihJam, $persenOp);
}

echo "\n--- SUM PER-AREA ---\n";
$sum = ['scanTotal' => 0, 'costTotal' => 0, 'powerTotal' => 0, 'penangananTotal' => 0, 'memberHours' => 0, 'reportNetHours' => 0, 'selisihJam' => 0];
foreach ($perAreaResults as $res) {
    foreach (['scanTotal','costTotal','powerTotal','penangananTotal','memberHours','reportNetHours','selisihJam'] as $k) {
        $sum[$k] += $res[$k];
    }
}
printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f\n",
    'TOTAL', $sum['scanTotal'], $sum['costTotal'], $sum['powerTotal'], $sum['penangananTotal'], $sum['memberHours'], $sum['reportNetHours'], $sum['selisihJam']);

echo "\n--- ALL AREAS (tanpa filter area) ---\n";
$scans = Scan::whereDate('Time_Scan', '>=', $from)->whereDate('Time_Scan', '<=', $to)->get();
$scanTotalAll = $scans->sum('Assigned_Hour_Scan') * (1 - 0.078);

$costTotalAll = Cost::whereDate('Start_Cost', '>=', $from)->whereDate('Start_Cost', '<=', $to)->sum('Non_Operational_Cost');

$powers = Power::whereDate('Start_Power', '>=', $from)->whereDate('Start_Power', '<=', $to)->get();
$powerTotalAll = $powers->sum('Leave_Hour_Power');

$penanganans = Penanganan::whereDate('Start_Penanganan', '>=', $from)->whereDate('Start_Penanganan', '<=', $to)->get();
$penangananTotalAll = $penanganans->sum('Hour_Penanganan');

$memberHoursAll = 0;
$cursor = $startDate->copy();
while ($cursor->lte($endDate)) {
    $dayStr = $cursor->format('Y-m-d');
    $reports = Report::where('Day_Report', $dayStr)->get();
    $dayHours = 0;
    foreach ($reports as $r) {
        $dayHours += (float) $r->Total_Hours_Report;
    }
    if ($dayStr === $todayStr) {
        $totalMembers = $reports->sum('Total_Member_Report');
        $dayHours = $totalMembers * 8.0;
    }
    $memberHoursAll += $dayHours;
    $cursor->addDay();
}

$reportNetHoursAll = $memberHoursAll - $powerTotalAll;
$kategori1 = $reportNetHoursAll + $penangananTotalAll;
$kategori2 = $scanTotalAll + $costTotalAll;
$selisihJamAll = $kategori2 - $kategori1;
$persenOpAll = $scanTotalAll != 0 ? ($selisihJamAll / $scanTotalAll) * 100 : 0;

printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f persen=%-6.2f%%\n",
    'ALL', $scanTotalAll, $costTotalAll, $powerTotalAll, $penangananTotalAll, $memberHoursAll, $reportNetHoursAll, $selisihJamAll, $persenOpAll);

echo "\n--- DIFF (All Areas - Sum Per-Area) ---\n";
printf("  scan:       %.10f\n", $scanTotalAll - $sum['scanTotal']);
printf("  cost:       %.10f\n", $costTotalAll - $sum['costTotal']);
printf("  power:      %.10f\n", $powerTotalAll - $sum['powerTotal']);
printf("  pen:        %.10f\n", $penangananTotalAll - $sum['penangananTotal']);
printf("  mh:         %.10f\n", $memberHoursAll - $sum['memberHours']);
printf("  net:        %.10f\n", $reportNetHoursAll - $sum['reportNetHours']);
printf("  selisih:    %.10f\n", $selisihJamAll - $sum['selisihJam']);
printf("  persenOp:   %.10f%%\n", $persenOpAll - $sum['persenOp']);
