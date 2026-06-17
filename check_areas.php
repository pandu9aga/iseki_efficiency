<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Models\Area;
use App\Models\Scan;
use App\Models\Cost;
use App\Models\Power;
use App\Models\Penanganan;
use App\Models\Report;

$areas = Area::orderByRaw("FIELD(Name_Area, 'TRANSMISI', 'SUB ENGINE', 'LINE A', 'LINE B', 'SUB ASSY', 'MAIN LINE', 'INSPEKSI', 'MOWER')")->pluck('Id_Area', 'Name_Area');
echo "=== AREAS IN ORDER LIST ===\n";
foreach ($areas as $name => $id) {
    echo "  $name => $id\n";
}

$allAreas = Area::all();
echo "\n=== ALL AREAS IN DB ===\n";
foreach ($allAreas as $a) {
    $inList = $areas->contains($a->Id_Area) ? 'YES' : '*** NOT IN LIST! ***';
    echo "  {$a->Name_Area} => {$a->Id_Area}  [$inList]\n";
}

echo "\n=== CHECK ORPHANED RECORDS (Id_Area not in areas table) ===\n";
$validIds = Area::pluck('Id_Area');

foreach (['scans' => new Scan, 'costs' => new Cost, 'powers' => new Power, 'penanganans' => new Penanganan] as $label => $model) {
    $orphans = $model->whereNotIn('Id_Area', $validIds)->count();
    echo "  $label: $orphans orphan records\n";
    if ($orphans > 0) {
        $sample = $model->whereNotIn('Id_Area', $validIds)->first();
        echo "    Sample Id_Area: " . ($sample->Id_Area ?? 'NULL') . "\n";
    }
}

echo "\n=== CHECK NULL Id_Area ===\n";
foreach (['scans' => new Scan, 'costs' => new Cost, 'powers' => new Power, 'penanganans' => new Penanganan] as $label => $model) {
    $nulls = $model->whereNull('Id_Area')->count();
    echo "  $label: $nulls null Id_Area\n";
}

echo "\n=== SAMPLE DATA FOR RANGE 2026-06-01 ~ 2026-06-10 ===\n";
$scanTotalAll = Scan::whereDate('Time_Scan', '>=', '2026-06-01')->whereDate('Time_Scan', '<=', '2026-06-10')->sum('Assigned_Hour_Scan');
echo "  All Areas scan total: $scanTotalAll\n";

$scanTotalPerArea = 0;
foreach ($allAreas as $a) {
    $st = Scan::where('Id_Area', $a->Id_Area)->whereDate('Time_Scan', '>=', '2026-06-01')->whereDate('Time_Scan', '<=', '2026-06-10')->sum('Assigned_Hour_Scan');
    echo "    {$a->Name_Area}: $st\n";
    $scanTotalPerArea += $st;
}
echo "  Sum per-area: $scanTotalPerArea\n";
echo "  Difference: " . ($scanTotalAll - $scanTotalPerArea) . "\n";
