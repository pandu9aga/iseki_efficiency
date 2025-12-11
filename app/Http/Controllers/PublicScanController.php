<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Tractor;
use App\Models\Plan;
use App\Models\Scan;

class PublicScanController extends Controller
{
    public function index()
    {
        return view('publics.scan');
    }

    public function verify(Request $request)
    {
        try {
            $request->validate([
                'qr_data' => 'required|string',
                'sequence_no' => 'required|string',
                'production_date' => 'required|string', // Format Ymd
                'tractor_name' => 'required|string',
            ]);

            $originalSequenceNo = $request->sequence_no; // Misal: "123" atau "T456"
            $productionDate = $request->production_date;
            $qrTractorName = $request->tractor_name;

            // 🔥 Tentukan sequence_no untuk pencarian: jika tidak ada T, pad jadi 5 digit
            $searchSequenceNo = $originalSequenceNo;
            if (!preg_match('/[T]/i', $originalSequenceNo)) {
                $searchSequenceNo = str_pad($originalSequenceNo, 5, '0', STR_PAD_LEFT); // "123" -> "00123"
            }

            // 🔥 Cek Plan di database podium menggunakan $searchSequenceNo
            $plan = Plan::where('Sequence_No_Plan', $searchSequenceNo)
                ->where('Production_Date_Plan', $productionDate)
                ->first();

            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'message' => "Plan tidak ditemukan untuk Sequence: $originalSequenceNo dan Production Date: $productionDate."
                ]);
            }

            return response()->json([
                'success' => true,
                'plan' => [
                    'Sequence_No_Plan' => $plan->Sequence_No_Plan, // Kirim dari database (bisa 00123 atau T456)
                    'Production_Date_Plan' => $plan->Production_Date_Plan,
                    'Model_Mower_Plan' => $plan->Model_Mower_Plan,
                    'Model_Collector_Plan' => $plan->Model_Collector_Plan,
                ],
                'qr_tractor_name' => $qrTractorName
            ]);

        } catch (\Exception $e) {
            \Log::error('Scan Verify Error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan internal server.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'Area_Scan' => 'required|string',
            'Name_Tractor' => 'required|string',
            'Sequence_No_Plan' => 'required|string',
            'Production_Date_Plan' => 'required|string',
            'Model_Mower_Plan' => 'nullable|string',
            'Model_Collector_Plan' => 'nullable|string',
        ]);

        $originalSequenceNo = $request->Sequence_No_Plan; // Bisa "123" atau "T456"
        $productionDate = $request->Production_Date_Plan;
        $modelMower = $request->Model_Mower_Plan;
        $modelCollector = $request->Model_Collector_Plan;

        $now = Carbon::now();
        $successCount = 0;

        // 🔥 Cek dan simpan jika Model_Mower_Plan ada
        if ($modelMower) {
            $mowerTractor = Tractor::where('Name_Tractor', $modelMower)->first();
            if ($mowerTractor) {
                Scan::create([
                    'Area_Scan' => $request->Area_Scan,
                    'Id_Tractor' => $mowerTractor->Id_Tractor,
                    'Time_Scan' => $now,
                    'Assigned_Hour_Scan' => $mowerTractor->Hour_Tractor,
                    'Sequence_No_Plan' => $originalSequenceNo, // 🔥 Simpan original sequence_no
                    'Production_Date_Plan' => $productionDate,
                ]);
                $successCount++;
            }
        }

        // 🔥 Cek dan simpan jika Model_Collector_Plan ada
        if ($modelCollector) {
            $collectorTractor = Tractor::where('Name_Tractor', $modelCollector)->first();
            if ($collectorTractor) {
                Scan::create([
                    'Area_Scan' => $request->Area_Scan,
                    'Id_Tractor' => $collectorTractor->Id_Tractor,
                    'Time_Scan' => $now,
                    'Assigned_Hour_Scan' => $collectorTractor->Hour_Tractor,
                    'Sequence_No_Plan' => $originalSequenceNo, // 🔥 Simpan original sequence_no
                    'Production_Date_Plan' => $productionDate,
                ]);
                $successCount++;
            }
        }

        if ($successCount === 0) {
            return redirect()->back()->with('error', 'Tidak ada Tractor yang cocok ditemukan untuk disimpan.');
        }

        $message = "$successCount entri scan berhasil disimpan.";
        return redirect()->back()->with('success', $message);
    }
}