<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Tractor;
use App\Models\Plan;
use App\Models\Scan;
use App\Models\Area;
use App\Models\DailyJob;
use App\Models\Member;

class PublicScanController extends Controller
{
    /**
     * Tampilkan halaman scan untuk Area.
     */
    public function index()
    {
        if (!session()->has('area_authenticated') || !session('area_authenticated')) {
            return redirect()->route('login.form')
                ->withErrors(['loginError' => 'Silakan login sebagai Area terlebih dahulu.']);
        }

        // Ambil data area dari sesi
        $areaId = session('area_id');
        $areaName = session('area_name');

        return view('publics.scan', compact('areaId', 'areaName'));
    }
    
    /**
     * Verifikasi data QR dari frontend (via AJAX).
     */
    public function verify(Request $request)
    {
        // 🔒 Cek sesi Area untuk request ini juga (penting untuk keamanan API)
        if (!session()->has('area_authenticated') || !session('area_authenticated')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'qr_data' => 'required|string',
                'sequence_no' => 'required|string',
                'production_date' => 'required|string',
                'tractor_name' => 'required|string',
            ]);

            $originalSequenceNo = $request->sequence_no;
            $productionDate = $request->production_date;
            $qrTractorName = $request->tractor_name;

            $searchSequenceNo = $originalSequenceNo;
            if (!preg_match('/[T]/i', $originalSequenceNo)) {
                $searchSequenceNo = str_pad($originalSequenceNo, 5, '0', STR_PAD_LEFT);
            }

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
                    'Sequence_No_Plan' => $plan->Sequence_No_Plan,
                    'Production_Date_Plan' => $plan->Production_Date_Plan,
                    'Model_Mower_Plan' => $plan->Model_Mower_Plan,
                    'Model_Collector_Plan' => $plan->Model_Collector_Plan,
                ],
                'qr_tractor_name' => $qrTractorName
            ]);
        } catch (\Exception $e) {
            \Log::error('Scan Verify Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan internal.'], 500);
        }
    }

    /**
     * Simpan hasil scan ke database.
     */
    public function store(Request $request)
    {
        // 🔒 Cek sesi Area sebelum menyimpan data
        if (!session()->has('area_authenticated') || !session('area_authenticated')) {
            return redirect()->route('login.form')
                ->withErrors(['loginError' => 'Sesi Area telah berakhir. Silakan login ulang.']);
        }

        $request->validate([
            'Id_Area' => 'required|integer|exists:areas,Id_Area',
            'Name_Tractor' => 'required|string',
            'Sequence_No_Plan' => 'required|string',
            'Production_Date_Plan' => 'required|string',
            'Model_Mower_Plan' => 'nullable|string',
            'Model_Collector_Plan' => 'nullable|string',
            'Nik_Replace' => 'nullable|string|max:16',
        ]);

        $originalSequenceNo = $request->Sequence_No_Plan;
        $productionDate = $request->Production_Date_Plan;
        $modelMower = $request->Model_Mower_Plan;
        $modelCollector = $request->Model_Collector_Plan;
        $areaId = $request->Id_Area;
        $nikReplace = trim($request->Nik_Replace ?? '');
        $nikReplace = $nikReplace === '' ? null : $nikReplace;

        // Ambil tanggal hari ini dalam format YYYYMMDD
        $scanDate = Carbon::now()->format('Ymd');

        $dailyJob = null;
        $idMemberAsli = null;
        $idDailyJob = null;

        if ($nikReplace) {
            $dailyJob = DailyJob::where('Production_Date_Plan', $scanDate)
                ->where('Id_Area', $areaId)
                ->where('Nik_Replace_Daily_Job', $nikReplace)
                ->first();

            if ($dailyJob) {
                $memberAsli = Member::where('nik', trim($dailyJob->Nik_Daily_Job))->first();
                $idMemberAsli = $memberAsli?->id;
                $idDailyJob = $dailyJob->Id_Daily_Job;
            }
            // Jika ingin strict: tolak jika NIK pengganti tidak valid
            // else {
            //     return back()->withErrors(['Nik_Replace' => 'NIK pengganti tidak terdaftar untuk area ini hari ini.']);
            // }
        }

        $now = Carbon::now();
        $successCount = 0;

        // Simpan scan untuk Mower
        if ($modelMower) {
            $mowerTractor = Tractor::where('Name_Tractor', $modelMower)->first();
            if ($mowerTractor) {
                Scan::create([
                    'Id_Area' => $areaId,
                    'Id_Tractor' => $mowerTractor->Id_Tractor,
                    'Time_Scan' => $now,
                    'Assigned_Hour_Scan' => $mowerTractor->Hour_Tractor,
                    'Sequence_No_Plan' => $originalSequenceNo,
                    'Production_Date_Plan' => $productionDate,
                    'Nik_Replace' => $nikReplace,
                    'Id_Member' => $idMemberAsli,
                    'Id_Daily_Job' => $idDailyJob,
                ]);
                $successCount++;
            }
        }

        // Simpan scan untuk Collector
        if ($modelCollector) {
            $collectorTractor = Tractor::where('Name_Tractor', $modelCollector)->first();
            if ($collectorTractor) {
                Scan::create([
                    'Id_Area' => $areaId,
                    'Id_Tractor' => $collectorTractor->Id_Tractor,
                    'Time_Scan' => $now,
                    'Assigned_Hour_Scan' => $collectorTractor->Hour_Tractor,
                    'Sequence_No_Plan' => $originalSequenceNo,
                    'Production_Date_Plan' => $productionDate,
                    'Nik_Replace' => $nikReplace,
                    'Id_Member' => $idMemberAsli,
                    'Id_Daily_Job' => $idDailyJob,
                ]);
                $successCount++;
            }
        }

        if ($successCount === 0) {
            return back()->with('error', 'Tidak ada Tractor yang cocok ditemukan.');
        }

        return back()->with('success', "$successCount entri scan berhasil disimpan.");
    }
}
