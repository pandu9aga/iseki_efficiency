<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tractor;
use App\Models\Area;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Illuminate\Support\Facades\Storage;

class TractorController extends Controller
{
    public function index()
    {
        $tractors = Tractor::all();
        $areas = Area::all();
        return view('admins.tractors.index', compact('tractors', 'areas'));
    }

    public function create()
    {
        $areas = Area::all();
        return view('admins.tractors.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'Name_Tractor' => 'required|string|max:255',
            'Group_Tractor' => 'required|string|max:255',
            'Hour_Tractor' => 'required|numeric|min:0',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        Tractor::create($request->only(['Name_Tractor', 'Group_Tractor', 'Hour_Tractor', 'Id_Area']));
        return redirect()->route('admins.tractors.index')->with('success', 'Tractor ditambahkan.');
    }

    public function edit(Tractor $tractor)
    {
        $areas = Area::all();
        return view('admins.tractors.edit', compact('tractor', 'areas'));
    }

    public function update(Request $request, Tractor $tractor)
    {
        $request->validate([
            'Name_Tractor' => 'required|string|max:255',
            'Group_Tractor' => 'required|string|max:255',
            'Hour_Tractor' => 'required|numeric|min:0',
            'Id_Area' => 'required|exists:areas,Id_Area',
        ]);

        $tractor->update($request->only(['Name_Tractor', 'Group_Tractor', 'Hour_Tractor', 'Id_Area']));
        return redirect()->route('admins.tractors.index')->with('success', 'Tractor diupdate.');
    }

    public function destroy(Tractor $tractor)
    {
        $tractor->delete();
        return redirect()->route('admins.tractors.index')->with('success', 'Tractor dihapus.');
    }

    // --- Import Excel/CSV ---
    public function importForm()
    {
        return view('admins.tractors.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();
        $extension = strtolower($file->getClientOriginalExtension());

        if (!file_exists($filePath)) {
            return back()->withErrors(['error' => 'File upload tidak ditemukan. Coba lagi.']);
        }

        try {
            if ($extension === 'csv') {
                $reader = ReaderEntityFactory::createCsvReader();
            } elseif (in_array($extension, ['xlsx', 'xls'])) {
                $reader = ReaderEntityFactory::createXlsxReader();
            } else {
                throw new \Exception('Format file tidak didukung.');
            }

            $reader->open($filePath);

            $dataInserted = 0;
            $sheetCount = 0;

            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetCount++;
                if ($sheetCount > 1) break; // hanya baca sheet pertama

                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->getCells();
                    $values = array_map(fn($cell) => $cell ? $cell->getValue() : null, $cells);

                    // Debug: log nilai yang dibaca
                    \Log::info('Row Values: ', $values);

                    $name = trim($values[0] ?? '');
                    $group = trim($values[1] ?? '');
                    $hourValue = $values[2] ?? null;
                    $areaName = trim($values[3] ?? ''); // Kolom ke-4 untuk Area

                    // Konversi jam ke float
                    if (is_numeric($hourValue)) {
                        $hour = (float) $hourValue;
                    } else {
                        $cleaned = preg_replace('/[^\d.]/', '', $hourValue);
                        $hour = is_numeric($cleaned) ? (float) $cleaned : 0.0;
                    }

                    if ($name === '' && $group === '') {
                        continue; // skip baris kosong
                    }

                    // Cari Area berdasarkan Name_Area (case insensitive)
                    $area = Area::whereRaw('LOWER(Name_Area) = ?', [strtolower($areaName)])->first();
                    
                    if (!$area) {
                        \Log::warning("Area tidak ditemukan: {$areaName}");
                        continue; // skip jika area tidak ditemukan
                    }

                    // Update atau create berdasarkan Name_Tractor dan Id_Area
                    Tractor::updateOrCreate(
                        [
                            'Name_Tractor' => $name,
                            'Id_Area' => $area->Id_Area,
                        ],
                        [
                            'Group_Tractor' => $group,
                            'Hour_Tractor' => $hour,
                        ]
                    );

                    $dataInserted++;
                }
            }

            $reader->close();

            return redirect()->route('admins.tractors.index')
                ->with('success', "Berhasil mengimpor {$dataInserted} data tractor.");
        } catch (\Exception $e) {
            \Log::error('Import error: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return back()->withErrors(['error' => 'Gagal mengimpor: ' . $e->getMessage()]);
        }
    }
}