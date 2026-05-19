<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\DailyJob;
use App\Models\Tractor;
use App\Models\Replacement;

class ReplacementController extends Controller
{
    /**
     * Tampilkan halaman awal untuk input NIK dan pilih PIC
     */
    public function start()
    {
        // Ambil data Daily Job hari ini untuk list PIC
        $today = Carbon::now()->format('Ymd');
        $dailyJobs = DailyJob::with('member')
            ->where('Production_Date_Plan', $today)
            ->get();

        return view('replacements.start', compact('dailyJobs'));
    }

    /**
     * Verifikasi NIK via AJAX
     * Hanya kembalikan field yang dibutuhkan frontend (nama & nik)
     */
    public function verifyNik(Request $request)
    {
        $request->validate([
            'nik' => 'required|numeric'
        ]);

        $member = Member::where('nik', $request->nik)->first();

        if ($member) {
            return response()->json([
                'success' => true,
                'member' => [
                    'nama' => $member->nama,
                    'nik' => $member->nik
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'NIK tidak ditemukan dalam data pegawai.'
        ]);
    }

    /**
     * Simpan sesi (NIK dan Id_Daily_Job) dan lanjut ke scan
     */
    public function storeStart(Request $request)
    {
        // Accept NIK from manual input or barcode scan
        $nik = $request->nik ?: $request->nik_scanned;

        if (!$nik || !is_numeric($nik)) {
            return back()->with('error', 'NIK harus diisi dan berupa angka.');
        }

        $request->validate([
            'id_daily_job' => 'required|integer|exists:daily_jobs,Id_Daily_Job'
        ]);

        $member = Member::where('nik', $nik)->first();
        if (!$member) {
            return back()->with('error', 'NIK tidak valid.');
        }

        // Simpan di session — gunakan $nik langsung (lebih eksplisit & aman)
        session([
            'replacement_nik' => $nik,
            'replacement_daily_job_id' => $request->id_daily_job
        ]);

        return redirect()->route('replacements.scan');
    }

    /**
     * Tampilkan halaman scan barcode traktor
     */
    public function scan()
    {
        // Jika tidak ada session, kembalikan ke start
        if (!session()->has('replacement_nik') || !session()->has('replacement_daily_job_id')) {
            return redirect()->route('replacements.start')
                ->with('error', 'Sesi berakhir atau data belum lengkap. Silakan mulai kembali.');
        }

        $dailyJobId = session('replacement_daily_job_id');
        $dailyJob = DailyJob::with('member')->find($dailyJobId);

        return view('replacements.scan', compact('dailyJob'));
    }

    /**
     * Proses hasil scan traktor
     */
    public function storeScan(Request $request)
    {
        // Cek sesi
        if (!session()->has('replacement_nik') || !session()->has('replacement_daily_job_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi berakhir. Silakan muat ulang halaman.'
            ], 401);
        }

        // 🔴 FIX: Re-validasi session data terhadap database
        $memberExists = Member::where('nik', session('replacement_nik'))->exists();
        $jobExists = DailyJob::find(session('replacement_daily_job_id'));

        if (!$memberExists || !$jobExists) {
            return response()->json([
                'success' => false,
                'message' => 'Data sesi tidak valid. Silakan mulai ulang.'
            ], 401);
        }

        $request->validate([
            'tractor_name' => 'required|string',
        ]);

        $rawQr = $request->tractor_name;
        $parts = explode(';', $rawQr);

        if (count($parts) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Format QR tidak valid.'
            ]);
        }

        $originalSequenceNo = trim($parts[0]);
        $productionDate = trim($parts[1]);
        $scannedTractorName = trim($parts[2]);

        // 🟡 FIX: Validasi format tiap part QR
        if (!preg_match('/^\d{8}$/', $productionDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal QR tidak valid (harus 8 digit: YYYYMMDD).'
            ]);
        }

        if (empty($scannedTractorName)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama traktor kosong dalam QR code.'
            ]);
        }

        $searchSequenceNo = $originalSequenceNo;
        if (!preg_match('/[T]/i', $originalSequenceNo)) {
            $searchSequenceNo = str_pad($originalSequenceNo, 5, '0', STR_PAD_LEFT);
        }

        // Cari plan di DB Podium
        $plan = \App\Models\Plan::where('Sequence_No_Plan', $searchSequenceNo)
            ->where('Production_Date_Plan', $productionDate)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan tidak ditemukan di database Podium.'
            ]);
        }

        // 🔴 FIX: Cek duplikat — cegah double insert untuk kombinasi yang sama
        $existing = Replacement::where([
            'NIK_Replacement'      => session('replacement_nik'),
            'Id_Daily_Job'         => session('replacement_daily_job_id'),
            'Sequence_No_Plan'     => $originalSequenceNo,
            'Production_Date_Plan' => $productionDate,
        ])->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Traktor ini sudah pernah di-scan sebelumnya.'
            ]);
        }

        // Simpan ke tabel replacements
        try {
            Replacement::create([
                'NIK_Replacement' => session('replacement_nik'),
                'Id_Daily_Job' => session('replacement_daily_job_id'),
                'Sequence_No_Plan' => $originalSequenceNo,
                'Production_Date_Plan' => $productionDate,
                'Model_Mower_Plan' => $plan->Model_Mower_Plan,
                'Model_Collector_Plan' => $plan->Model_Collector_Plan,
                'Name_Tractor' => $scannedTractorName
            ]);

            return response()->json([
                'success' => true,
                'tractor_name' => $scannedTractorName,
                'sequence_no' => $originalSequenceNo,
                'message' => 'Data berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Tampilkan halaman input total jam pergantian (dalam menit)
     */
    public function inputDuration()
    {
        if (!session()->has('replacement_nik') || !session()->has('replacement_daily_job_id')) {
            return redirect()->route('replacements.start')
                ->with('error', 'Sesi berakhir atau data belum lengkap. Silakan mulai kembali.');
        }

        $dailyJobId = session('replacement_daily_job_id');
        $dailyJob = DailyJob::with('member')->find($dailyJobId);

        return view('replacements.input_duration', compact('dailyJob'));
    }

    /**
     * Simpan durasi (menit) dan selesaikan sesi
     */
    public function storeDuration(Request $request)
    {
        if (!session()->has('replacement_nik') || !session()->has('replacement_daily_job_id')) {
            return redirect()->route('replacements.start')->with('error', 'Sesi berakhir.');
        }

        $request->validate([
            'total_minutes' => 'required|integer|min:1'
        ]);

        $nik = session('replacement_nik');
        $dailyJobId = session('replacement_daily_job_id');
        
        if ($request->total_minutes > 0) {
            \App\Models\ReplacementDuration::create([
                'NIK_Replacement' => $nik,
                'Id_Daily_Job' => $dailyJobId,
                'Total_Minutes' => $request->total_minutes,
            ]);
        }

        session()->forget(['replacement_nik', 'replacement_daily_job_id']);
        return redirect()->route('login.form')->with('success', 'Proses pergantian selesai dan durasi berhasil disimpan.');
    }

    /**
     * Keluar dari proses scan dan bersihkan sesi (opsional jika lewati)
     */
    public function finish()
    {
        session()->forget(['replacement_nik', 'replacement_daily_job_id']);
        return redirect()->route('login.form')->with('success', 'Proses pergantian selesai.');
    }
}
