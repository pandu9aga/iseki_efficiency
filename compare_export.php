<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use App\Http\Controllers\Admin\AdminController;
use App\Models\Area;
use PhpOffice\PhpSpreadsheet\IOFactory;

$_GET['from'] = '2026-03-01';
$_GET['to'] = '2026-04-04';
$_SERVER['REQUEST_URI'] = '/admins/dashboard/export?from=2026-03-01&to=2026-04-04';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

$controller = new AdminController();

$ref = new ReflectionMethod($controller, 'export');
$ref->setAccessible(true);
$response = $ref->invoke($controller, request());

// Get the temp file path from the response
$file = $response->getFile();
$path = $file->getPathname();
echo "Export file: $path\n";

// Read the Excel file
$spreadsheet = IOFactory::load($path);
$sheet = $spreadsheet->getActiveSheet();

echo "\n=== EXCEL CONTENT ===\n";
for ($row = 1; $row <= 30; $row++) {
    $a = $sheet->getCell("A$row")->getValue();
    $b = $sheet->getCell("B$row")->getValue();
    $c = $sheet->getCell("C$row")->getValue();
    
    $aStr = is_null($a) ? '' : (is_string($a) ? $a : (is_object($a) ? get_class($a) : (string)$a));
    $bStr = is_null($b) ? '' : (is_string($b) ? $b : (string)$b);
    $cStr = is_null($c) ? '' : (is_string($c) ? $c : (string)$c);
    
    if ($aStr !== '' || $bStr !== '' || $cStr !== '') {
        echo "Row $row: A='$aStr' B='$bStr' C='$cStr'\n";
    }
}

echo "\n=== CALCULATED VALUES (Excel would show) ===\n";
// Read the calculated values
for ($row = 8; $row <= 30; $row++) {
    $a = $sheet->getCell("A$row")->getValue();
    $b = $sheet->getCell("B$row")->getCalculatedValue();
    $c = $sheet->getCell("C$row")->getCalculatedValue();
    
    $aStr = is_null($a) ? '' : (is_string($a) ? $a : (string)$a);
    if ($aStr !== '') {
        echo "Row $row: $aStr => B=$b, C=$c\n";
    }
}
