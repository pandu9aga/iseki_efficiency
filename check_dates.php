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

$dateCols = [
    'scans' => 'Time_Scan',
    'costs' => 'Start_Cost',
    'powers' => 'Start_Power',
    'penanganans' => 'Start_Penanganan',
    'reports' => 'Day_Report',
];

echo "=== DATE RANGES WITH DATA ===\n\n";

foreach ($dateCols as $label => $dateCol) {
    $model = match($label) {
        'scans' => new Scan,
        'costs' => new Cost,
        'powers' => new Power,
        'penanganans' => new Penanganan,
        'reports' => new Report,
    };
    $first = $model->orderBy($dateCol, 'asc')->first();
    $last = $model->orderBy($dateCol, 'desc')->first();
    
    echo "$label: ";
    if ($first) {
        $f = $first->$dateCol ?? 'N/A';
        $l = $last->$dateCol ?? 'N/A';
        $count = $model->count();
        echo "$count records, $f ~ $l\n";
    } else {
        echo "NO DATA\n";
    }
}

echo "\n=== REPORT DISTRIBUTION (last 10) ===\n";
$reports = Report::selectRaw('Day_Report, COUNT(*) as cnt, SUM(Total_Member_Report) as members, SUM(Total_Hours_Report) as hours')
    ->groupBy('Day_Report')
    ->orderBy('Day_Report', 'desc')
    ->limit(10)
    ->get();
    
foreach ($reports as $r) {
    echo "  {$r->Day_Report}: {$r->cnt} areas, {$r->members} total members, {$r->hours} total hours\n";
}

echo "\n=== SCAN DISTRIBUTION (last 10) ===\n";
$scans = Scan::selectRaw('DATE(Time_Scan) as day, COUNT(*) as cnt, SUM(Assigned_Hour_Scan) as hours')
    ->groupBy('day')
    ->orderBy('day', 'desc')
    ->limit(10)
    ->get();
    
foreach ($scans as $s) {
    echo "  {$s->day}: {$s->cnt} scans, {$s->hours} hours\n";
}

echo "\n=== COST DISTRIBUTION (last 10) ===\n";
$costs = Cost::selectRaw('DATE(Start_Cost) as day, COUNT(*) as cnt, SUM(Non_Operational_Cost) as hours')
    ->groupBy('day')
    ->orderBy('day', 'desc')
    ->limit(10)
    ->get();
    
foreach ($costs as $c) {
    echo "  {$c->day}: {$c->cnt} costs, {$c->hours} hours\n";
}
