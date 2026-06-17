<?php
$_GET['from'] = '2026-03-01';
$_GET['to'] = '2026-04-04';
$_SERVER['REQUEST_URI'] = '/admins/dashboard?from=2026-03-01&to=2026-04-04';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Http\Controllers\Admin\AdminController;
use App\Models\Area;

$controller = new AdminController();

$ref = new ReflectionMethod($controller, 'resolveDateFilter');
$ref->setAccessible(true);
$filter = $ref->invoke($controller, request());
extract($filter);

echo "=== FILTER ===\n";
echo "filterMode: $filterMode\n";
echo "startDate: {$startDate->format('Y-m-d H:i:s')}\n";
echo "endDate: {$endDate->format('Y-m-d H:i:s')}\n";
echo "dateString: $dateString\n";
echo "isToday: " . ($isToday ? 'true' : 'false') . "\n";

$areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->get();
$areaId = request()->query('area');

echo "\n=== ALL AREAS (tanpa filter area) ===\n";
$buildQ = new ReflectionMethod($controller, 'buildDateQueries');
$buildQ->setAccessible(true);
$queries = $buildQ->invoke($controller, $isRangeFilter, $isMonthFilter, $startDate, $endDate, $dateString);
extract($queries);

$scanQuery->with('tractor');
$powerQuery->with('member');

$scans = $scanQuery->get();
$costs = $costQuery->get();
$powers = $powerQuery->get();
$penanganans = $penanganansQuery->get();

$scanTotalRaw = $scans->sum('Assigned_Hour_Scan');
$scanTotalKaizen = $scanTotalRaw * (1 - 0.078);
$costTotal = $costs->sum('Non_Operational_Cost');
$powerTotal = $powers->sum('Leave_Hour_Power');
$penangananTotal = $penanganans->sum('Hour_Penanganan');

$calcMH = new ReflectionMethod($controller, 'calcMemberHoursRange');
$calcMH->setAccessible(true);
$mhResult = $calcMH->invoke($controller, $startDate, $endDate, $areas, null);

echo "scanTotal (raw): $scanTotalRaw\n";
echo "scanTotal (kaizen 7.8%): $scanTotalKaizen\n";
echo "costTotal: $costTotal\n";
echo "powerTotal: $powerTotal\n";
echo "penangananTotal: $penangananTotal\n";
echo "memberHours: {$mhResult['memberHours']}\n";
echo "reportMembers: {$mhResult['reportMembers']}\n";

$reportNetHours = $mhResult['memberHours'] - $powerTotal;
$kategori1 = $reportNetHours + $penangananTotal;
$kategori2 = $scanTotalKaizen + $costTotal;
$selisihJam = $kategori2 - $kategori1;
$persenOp = $scanTotalKaizen != 0 ? ($selisihJam / $scanTotalKaizen) * 100 : 0;

echo "\n=== EFISIENSI (All Areas) ===\n";
echo "reportNetHours (memberHours - power): $reportNetHours\n";
echo "kategori1 (netHours + penanganan): $kategori1\n";
echo "kategori2 (scanKaizen + cost): $kategori2\n";
echo "selisihJam (kategori2 - kategori1): $selisihJam\n";
echo "persenOperasional: {$persenOp}%\n";

echo "\n\n=== PER AREA BREAKDOWN ===\n";
$perAreaSum = ['scanKaizen' => 0, 'cost' => 0, 'power' => 0, 'pen' => 0, 'mh' => 0, 'net' => 0, 'selisih' => 0];
foreach ($areas as $area) {
    $q = $buildQ->invoke($controller, $isRangeFilter, $isMonthFilter, $startDate, $endDate, $dateString);
    $sq = $q['scanQuery']->with('tractor')->where('Id_Area', $area->Id_Area);
    $cq = $q['costQuery']->where('Id_Area', $area->Id_Area);
    $pq = $q['powerQuery']->with('member')->where('Id_Area', $area->Id_Area);
    $penq = $q['penanganansQuery']->where('Id_Area', $area->Id_Area);
    
    $sc = $sq->get()->sum('Assigned_Hour_Scan') * (1 - 0.078);
    $co = $cq->sum('Non_Operational_Cost');
    $po = $pq->sum('Leave_Hour_Power');
    $pe = $penq->sum('Hour_Penanganan');
    $mh = $calcMH->invoke($controller, $startDate, $endDate, $areas, (string) $area->Id_Area);
    $net = $mh['memberHours'] - $po;
    $sel = ($sc + $co) - ($net + $pe);
    
    $perAreaSum['scanKaizen'] += $sc;
    $perAreaSum['cost'] += $co;
    $perAreaSum['power'] += $po;
    $perAreaSum['pen'] += $pe;
    $perAreaSum['mh'] += $mh['memberHours'];
    $perAreaSum['net'] += $net;
    $perAreaSum['selisih'] += $sel;
    
    printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f\n",
        $area->Name_Area, $sc, $co, $po, $pe, $mh['memberHours'], $net, $sel);
}

echo "\n--- SUM PER-AREA ---\n";
printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f\n",
    'TOTAL', $perAreaSum['scanKaizen'], $perAreaSum['cost'], $perAreaSum['power'], $perAreaSum['pen'],
    $perAreaSum['mh'], $perAreaSum['net'], $perAreaSum['selisih']);

echo "\n--- ALL AREAS ---\n";
printf("  %-12s scan=%-8.2f cost=%-8.2f power=%-8.2f pen=%-8.2f mh=%-8.2f net=%-8.2f selisih=%-8.2f\n",
    'ALL', $scanTotalKaizen, $costTotal, $powerTotal, $penangananTotal,
    $mhResult['memberHours'], ($mhResult['memberHours'] - $powerTotal), $selisihJam);

echo "\n=== DIFF (All Areas - Sum Per-Area) ===\n";
printf("  scan:       %.10f\n", $scanTotalKaizen - $perAreaSum['scanKaizen']);
printf("  cost:       %.10f\n", $costTotal - $perAreaSum['cost']);
printf("  power:      %.10f\n", $powerTotal - $perAreaSum['power']);
printf("  pen:        %.10f\n", $penangananTotal - $perAreaSum['pen']);
printf("  mh:         %.10f\n", $mhResult['memberHours'] - $perAreaSum['mh']);
printf("  net:        %.10f\n", ($mhResult['memberHours'] - $powerTotal) - $perAreaSum['net']);
printf("  selisih:    %.10f\n", $selisihJam - $perAreaSum['selisih']);
