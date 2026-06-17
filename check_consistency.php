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

echo "=== VERIFICATION: Range $from ~ $to ===\n\n";

echo "--- SCANS (All Areas) ---\n";
$scansAll = Scan::whereDate('Time_Scan', '>=', $from)->whereDate('Time_Scan', '<=', $to)
    ->selectRaw('SUM(Assigned_Hour_Scan) as total')
    ->first();
echo "  All Areas total: {$scansAll->total}\n";

$scanSum = 0;
foreach ($areas as $a) {
    $st = Scan::where('Id_Area', $a->Id_Area)
        ->whereDate('Time_Scan', '>=', $from)->whereDate('Time_Scan', '<=', $to)
        ->sum('Assigned_Hour_Scan');
    echo "  {$a->Name_Area}: $st\n";
    $scanSum += $st;
}
echo "  Sum per-area: $scanSum\n";
echo "  Diff: " . ($scansAll->total - $scanSum) . "\n\n";

echo "--- COSTS (All Areas) ---\n";
$costsAll = Cost::whereDate('Start_Cost', '>=', $from)->whereDate('Start_Cost', '<=', $to)
    ->selectRaw('SUM(Non_Operational_Cost) as total')
    ->first();
echo "  All Areas total: {$costsAll->total}\n";

$costSum = 0;
foreach ($areas as $a) {
    $ct = Cost::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Cost', '>=', $from)->whereDate('Start_Cost', '<=', $to)
        ->sum('Non_Operational_Cost');
    echo "  {$a->Name_Area}: $ct\n";
    $costSum += $ct;
}
echo "  Sum per-area: $costSum\n";
echo "  Diff: " . ($costsAll->total - $costSum) . "\n\n";

echo "--- POWERS (All Areas) ---\n";
$powersAll = Power::whereDate('Start_Power', '>=', $from)->whereDate('Start_Power', '<=', $to)
    ->selectRaw('SUM(Leave_Hour_Power) as total')
    ->first();
echo "  All Areas total: {$powersAll->total}\n";

$powerSum = 0;
foreach ($areas as $a) {
    $pt = Power::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Power', '>=', $from)->whereDate('Start_Power', '<=', $to)
        ->sum('Leave_Hour_Power');
    echo "  {$a->Name_Area}: $pt\n";
    $powerSum += $pt;
}
echo "  Sum per-area: $powerSum\n";
echo "  Diff: " . ($powersAll->total - $powerSum) . "\n\n";

echo "--- PENANGANAN (All Areas) ---\n";
$penAll = Penanganan::whereDate('Start_Penanganan', '>=', $from)->whereDate('Start_Penanganan', '<=', $to)
    ->selectRaw('SUM(Hour_Penanganan) as total')
    ->first();
echo "  All Areas total: {$penAll->total}\n";

$penSum = 0;
foreach ($areas as $a) {
    $pt = Penanganan::where('Id_Area', $a->Id_Area)
        ->whereDate('Start_Penanganan', '>=', $from)->whereDate('Start_Penanganan', '<=', $to)
        ->sum('Hour_Penanganan');
    echo "  {$a->Name_Area}: $pt\n";
    $penSum += $pt;
}
echo "  Sum per-area: $penSum\n";
echo "  Diff: " . ($penAll->total - $penSum) . "\n\n";

echo "=== MEMBER HOURS CHECK ===\n";
echo "Looping each day $from ~ $to...\n";
$allAreaMembers = 0;
$allAreaHours = 0;
$perAreaMembers = [];
$perAreaHours = [];
foreach ($areas as $a) {
    $perAreaMembers[(string)$a->Id_Area] = 0;
    $perAreaHours[(string)$a->Id_Area] = 0.0;
}

$cursor = Carbon::parse($from)->startOfDay();
$end = Carbon::parse($to)->endOfDay();
while ($cursor->lte($end)) {
    $dayStr = $cursor->format('Y-m-d');
    $dayReports = Report::where('Day_Report', $dayStr)->get()->keyBy('Id_Area');
    
    // All Areas
    $dayAllMembers = 0;
    $dayAllHours = 0.0;
    foreach ($areas as $a) {
        $r = $dayReports->get($a->Id_Area);
        if ($r) {
            $dayAllMembers += (int)$r->Total_Member_Report;
            $dayAllHours += (float)$r->Total_Hours_Report;
        }
    }
    $allAreaMembers += $dayAllMembers;
    $allAreaHours += $dayAllHours;
    
    // Per area
    foreach ($areas as $a) {
        $r = $dayReports->get($a->Id_Area);
        if ($r) {
            $perAreaMembers[(string)$a->Id_Area] += (int)$r->Total_Member_Report;
            $perAreaHours[(string)$a->Id_Area] += (float)$r->Total_Hours_Report;
        }
    }
    
    $cursor->addDay();
}

echo "All Areas: $allAreaMembers members, $allAreaHours hours\n";
$sumMembers = array_sum($perAreaMembers);
$sumHours = array_sum($perAreaHours);
echo "Sum per-area: $sumMembers members, $sumHours hours\n";
echo "Member diff: " . ($allAreaMembers - $sumMembers) . "\n";
echo "Hours diff: " . ($allAreaHours - $sumHours) . "\n\n";

foreach ($areas as $a) {
    echo "  {$a->Name_Area}: {$perAreaMembers[(string)$a->Id_Area]} members, {$perAreaHours[(string)$a->Id_Area]} hours\n";
}
